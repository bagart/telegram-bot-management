<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Mcp\Tools;

use BAGArt\TelegramBot\Contracts\Outbound\AtomicDlqQueueContract;
use BAGArt\TelegramBot\Contracts\Outbound\ChannelDiscoverableQueueContract;
use BAGArt\TelegramBot\Contracts\Outbound\OutboundQueueContract;
use BAGArt\TelegramBot\Outbound\Config\OutboundWorkerConfig;
use BAGArt\TelegramBot\Outbound\DeadLetterEntry;
use BAGArt\TelegramBot\Outbound\TgOutboundStats;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * Retry a single DLQ entry. Mirrors `tgbm:outbound-dlq --retry={entryId}`.
 *
 * Atomically removes the entry from its DLQ channel, restores the envelope, and
 * re-pushes the task to the main queue. Respects max_dlq_redeliveries.
 */
#[IsDestructive]
class DlqRetry extends Tool
{
    protected string $description = 'Retry a specific Dead Letter Queue entry by ID. Atomically extracts the entry from its DLQ channel, restores the task, and re-pushes it to the main outbound queue for reprocessing. Refuses if the entry has exceeded max_dlq_redeliveries (default 3). Destructive: mutates DLQ and queue state — confirm with the user before calling.';

    public function __construct(
        private readonly OutboundQueueContract $queue,
        private readonly TgOutboundStats $stats,
        private readonly OutboundWorkerConfig $workerConfig,
    ) {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entry_id' => $schema->string()
                ->description('The DLQ entry ID (= the original OutboundTask ID). Find it via the dlq-list tool.')
                ->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        if (!$this->queue instanceof AtomicDlqQueueContract) {
            return Response::error(
                'The current queue adapter does not support DLQ operations (requires AtomicDlqQueueContract).'
            );
        }

        if (!$this->queue instanceof ChannelDiscoverableQueueContract) {
            return Response::error(
                'The current queue adapter does not support channel discovery (requires ChannelDiscoverableQueueContract).'
            );
        }

        $entryId = trim((string)$request->string('entry_id'));
        if ($entryId === '') {
            return Response::error('entry_id is required.');
        }

        $channels = $this->queue->getDlqChannels('tg-dlq:*');

        foreach ($channels as $channel) {
            $raw = $this->queue->atomicFetchAndRemoveFromDlq($channel, $entryId);
            if ($raw === null) {
                continue;
            }

            $entryData = json_decode($raw, true);
            if (!is_array($entryData)) {
                continue;
            }

            $entry = DeadLetterEntry::fromJson($entryData);

            if (!$entry->canRedeliver($this->workerConfig->maxDlqRedeliveries)) {
                return Response::error(
                    "Entry {$entryId} has exceeded max redeliveries ({$this->workerConfig->maxDlqRedeliveries})."
                );
            }

            $envelope = $entry->restoreEnvelope();
            $this->queue->push($envelope->task);
            $this->stats->recordDlqRetried($envelope->task->botConfig->botId);

            return Response::json([
                'retried' => $entryId,
                'channel' => $channel,
                'bot_id' => $envelope->task->botConfig->botId,
                'dto_class' => $envelope->task->dtoClass,
            ]);
        }

        return Response::error("DLQ entry {$entryId} not found.");
    }
}
