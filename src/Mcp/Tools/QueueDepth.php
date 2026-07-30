<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Mcp\Tools;

use BAGArt\TelegramBot\Contracts\Outbound\OutboundQueueContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Outbound queue depth (ready + delayed tasks, excluding in-flight).
 *
 * The base {@see OutboundQueueContract} uses a single global channel (`tg-outbound`),
 * so depth is platform-wide, not per-bot. The optional bot_id argument is accepted for
 * forward compatibility but currently does not filter.
 */
#[IsReadOnly]
class QueueDepth extends Tool
{
    protected string $description = 'Get the outbound queue depth — the number of ready + delayed Telegram outbound tasks waiting to be sent (excludes in-flight). Platform-wide; a growing value suggests the daemon is stalled or under-provisioned.';

    public function __construct(private readonly OutboundQueueContract $queue)
    {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'bot_id' => $schema->string()
                ->description(
                    'Optional bot ID. The base queue is a single global channel, so this is accepted for forward compatibility but does not currently filter the count.'
                ),
        ];
    }

    public function handle(Request $request): Response
    {
        $size = $this->queue->size();

        return Response::json([
            'size' => $size,
            'channel' => 'tg-outbound',
            'note' => 'Global queue depth (ready + delayed). In-flight tasks are not counted.',
        ]);
    }
}
