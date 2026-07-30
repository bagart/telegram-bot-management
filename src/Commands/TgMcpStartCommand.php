<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Thin launcher for the tg-ops MCP server, mirroring `boost:mcp`.
 * The actual server is registered as `tg-ops` in TelegramBotManagementServiceProvider::boot()
 * and started via the framework's `mcp:start` command.
 */
#[AsCommand('tgbm:mcp', 'Start the Telegram Ops MCP server (queue/DLQ/metrics tools)')]
class TgMcpStartCommand extends Command
{
    public function handle(): int
    {
        return (int)Artisan::call('mcp:start', ['handle' => 'tg-ops']);
    }
}
