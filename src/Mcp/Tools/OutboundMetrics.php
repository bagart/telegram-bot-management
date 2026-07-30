<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Mcp\Tools;

use BAGArt\TelegramBot\Outbound\TgOutboundStats;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Outbound throughput metrics. Mirrors `tgbm:outbound-metrics`.
 *
 * Hours use the YmdH format (e.g. 2026072714 for 2026-07-27 14:00). If no range is
 * given, returns the 24h state snapshot via {@see TgOutboundStats::getState()}.
 */
#[IsReadOnly]
class OutboundMetrics extends Tool
{
    protected string $description = 'Read outbound throughput metrics: sent, retries, failures, DLQ pushes. Hour buckets use YmdH format (e.g. 2026072714). With no range, returns a 24h state snapshot; with bot_id, returns per-bot metrics; with from_hour/to_hour, returns a custom range.';

    public function __construct(private readonly TgOutboundStats $stats)
    {
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'bot_id' => $schema->string()
                ->description('Optional bot ID for per-bot metrics. Omit for global metrics.'),
            'from_hour' => $schema->string()
                ->description('Start hour in YmdH format (e.g. 2026072714). Omit to use the 24h state snapshot.'),
            'to_hour' => $schema->string()
                ->description('End hour (inclusive) in YmdH format. Omit to use the 24h state snapshot.'),
        ];
    }

    public function handle(Request $request): Response
    {
        if (!$request->hasAny(['from_hour', 'to_hour'])) {
            return Response::json([
                'mode' => 'state_24h',
                'state' => $this->stats->getState(),
            ]);
        }

        $fromHour = (string)$request->string('from_hour');
        $toHour = $request->has('to_hour')
            ? (string)$request->string('to_hour')
            : date('YmdH');

        if (!preg_match('/^\d{10}$/', $fromHour) || !preg_match('/^\d{10}$/', $toHour)) {
            return Response::error('from_hour and to_hour must be 10-digit YmdH (e.g. 2026072714).');
        }

        if ($request->has('bot_id')) {
            $metrics = $this->stats->getBotMetrics((string)$request->string('bot_id'), $fromHour, $toHour);
            $mode = 'per_bot';
            $botId = $request->string('bot_id');
        } else {
            $metrics = $this->stats->getGlobalMetrics($fromHour, $toHour);
            $mode = 'global';
            $botId = null;
        }

        return Response::json([
            'mode' => $mode,
            'bot_id' => $botId,
            'from_hour' => $fromHour,
            'to_hour' => $toHour,
            'metrics' => $metrics,
        ]);
    }
}
