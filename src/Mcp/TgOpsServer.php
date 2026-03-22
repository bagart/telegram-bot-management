<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Telegram operations MCP server.
 *
 * Exposes queue depth, DLQ, metrics, bot-list and daemon-status tools so an agent
 * can observe and operate the running platform without shelling out to artisan.
 * Mirrors the {@see \Laravel\Boost\Mcp\Boost} pattern: a thin Server subclass that
 * just declares its tool list in boot().
 */
#[Name('Telegram Ops')]
#[Version('0.0.1')]
#[Instructions('Operational tools for the Telegram bot platform: outbound queue depth, DLQ inspection/retry/purge, hourly metrics, registered bots, and an indirect daemon-liveness signal. Read tools are safe; DlqRetry and DlqPurge are destructive and require confirmation.')]
class TgOpsServer extends Server
{
    protected function boot(): void
    {
        $this->tools = [
            Tools\QueueDepth::class,
            Tools\DlqList::class,
            Tools\OutboundMetrics::class,
            Tools\BotList::class,
            Tools\DaemonStatus::class,
            Tools\DlqRetry::class,
            Tools\DlqPurge::class,
        ];
    }
}
