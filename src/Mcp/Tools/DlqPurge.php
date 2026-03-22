<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Mcp\Tools;

use BAGArt\TelegramBot\Contracts\Outbound\AtomicDlqQueueContract;
use BAGArt\TelegramBot\Contracts\Outbound\ChannelDiscoverableQueueContract;
use BAGArt\TelegramBot\Contracts\Outbound\OutboundQueueContract;
use BAGArt\TelegramBot\Contracts\Outbound\PurgeableQueueContract;
use BAGArt\TelegramBot\Outbound\TgOutboundStats;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * Purge expired DLQ entries. Mirrors `tgbm:outbound-dlq --purge --before={days}`.
 *
 * Requires the queue to implement PurgeableQueueContract (in addition to the DLQ
 * capability interfaces).
 */
#[IsDestructive]
class DlqPurge extends Tool
{
    protected string $description = 'Purge Dead Letter Queue entries older than a given number of days. Permanently deletes expired entries across all bot DLQ channels. Destructive: data loss is irreversible — confirm the retention window with the user before calling.';

    public function __construct(
        private readonly OutboundQueueContract $queue,
        private readonly TgOutboundStats $stats,
    ) {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'older_than_days' => $schema->integer()
                ->description('Delete DLQ entries whose failedAt timestamp is older than this many days. Default 30.')
                ->required(),
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

        if (!$this->queue instanceof PurgeableQueueContract) {
            return Response::error('The current queue adapter does not support purge (requires PurgeableQueueContract).');
        }

        $days = $request->integer('older_than_days');
        if ($days < 0) {
            return Response::error('older_than_days must be a non-negative integer.');
        }

        $beforeTimestamp = time() - ($days * 86400);
        $purged = $this->queue->purgeExpired('tg-dlq:*', $beforeTimestamp);
        $this->stats->recordDlqPurged($purged);

        return Response::json([
            'purged' => $purged,
            'older_than_days' => $days,
        ]);
    }
}
