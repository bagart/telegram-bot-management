<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Mcp\Tools;

use BAGArt\TelegramBot\Contracts\Outbound\AtomicDlqQueueContract;
use BAGArt\TelegramBot\Contracts\Outbound\ChannelDiscoverableQueueContract;
use BAGArt\TelegramBot\Contracts\Outbound\OutboundQueueContract;
use BAGArt\TelegramBot\Outbound\Config\OutboundWorkerConfig;
use BAGArt\TelegramBot\Outbound\DeadLetterEntry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Inspect the Dead Letter Queue. Mirrors `tgbm:outbound-dlq --list`.
 */
#[IsReadOnly]
class DlqList extends Tool
{
    protected string $description = 'List Dead Letter Queue entries (tasks that failed permanently: business errors, expired tasks, exhausted retry budget). Optionally filter by bot_id. Returns id, reason, failure time, task DTO class, redelivery count, and whether the entry can still be retried (capped at max_dlq_redeliveries).';

    public function __construct(
        private readonly OutboundQueueContract $queue,
        private readonly OutboundWorkerConfig $workerConfig,
    ) {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'bot_id' => $schema->string()
                ->description('Optional bot ID to filter DLQ entries (matches the tg-dlq:{botId} channel).'),
            'limit' => $schema->integer()
                ->description('Maximum number of entries to return (default 50).'),
        ];
    }

    public function handle(Request $request): Response
    {
        if (!$this->queue instanceof AtomicDlqQueueContract) {
            return Response::error('The current queue adapter does not support DLQ operations (requires AtomicDlqQueueContract).');
        }

        if (!$this->queue instanceof ChannelDiscoverableQueueContract) {
            return Response::error('The current queue adapter does not support channel discovery (requires ChannelDiscoverableQueueContract).');
        }

        $botId = $request->has('bot_id') ? (string) $request->string('bot_id') : null;
        $limit = $request->has('limit') ? max(1, $request->integer('limit')) : 50;

        $channels = $this->queue->getDlqChannels('tg-dlq:*');
        if ($botId !== null && $botId !== '') {
            $channels = array_values(array_filter(
                $channels,
                static fn (string $ch): bool => str_ends_with($ch, $botId),
            ));
            if ($channels === []) {
                return Response::json(['entries' => [], 'note' => "No DLQ channel found for bot: {$botId}"]);
            }
        }

        $targetChannel = $channels !== [] ? $channels[0] : null;
        $entries = $this->queue->listDeadLetter($targetChannel, $limit);

        $data = array_map(fn (DeadLetterEntry $e): array => [
            'id' => $e->id,
            'reason' => $e->reason,
            'failed_at' => $e->failedAt,
            'task_class' => $e->originalTask['dtoClass'] ?? 'unknown',
            'redelivery_count' => $e->redeliveryCount,
            'can_redeliver' => $e->canRedeliver($this->workerConfig->maxDlqRedeliveries),
        ], $entries);

        return Response::json([
            'entries' => $data,
            'count' => count($data),
            'channel' => $targetChannel ?? 'all',
        ]);
    }
}
