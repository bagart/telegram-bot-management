<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement;

use Illuminate\Support\ServiceProvider;

class TelegramBotManagementServiceProvider extends ServiceProvider
{
    protected array $commands = [
        \BAGArt\TelegramBotManagement\Commands\TgBMMonitorCommand::class,
        \BAGArt\TelegramBotManagement\Commands\TgBMPollerCommand::class,
        \BAGArt\TelegramBotManagement\Commands\TgBotManagerMigrate::class,
        \BAGArt\TelegramBotManagement\Commands\TgBotManagerInit::class,
        \BAGArt\TelegramBotManagement\Commands\TgBMAuditCommand::class,
        \BAGArt\TelegramBotManagement\Commands\TgOutboundDaemonCommand::class,
        \BAGArt\TelegramBotManagement\Commands\TgOutboundMetricsCommand::class,
        \BAGArt\TelegramBotManagement\Commands\TgOutboundDlqCommand::class,
        \BAGArt\TelegramBotManagement\Commands\TgOutboundToolCommand::class,
    ];

    /**
     * Commands that only exist when laravel/mcp is installed. Registered
     * conditionally so the lib doesn't hard-fail if mcp is absent.
     */
    protected array $mcpCommands = [
        \BAGArt\TelegramBotManagement\Commands\TgMcpStartCommand::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/tg-outbound-daemon.php',
            'tg-outbound-daemon',
        );

        $this->commands($this->commands);

        if (class_exists(\Laravel\Mcp\Facades\Mcp::class)) {
            $this->commands($this->mcpCommands);
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if (class_exists(\Laravel\Mcp\Facades\Mcp::class)) {
            \Laravel\Mcp\Facades\Mcp::local('tg-ops', \BAGArt\TelegramBotManagement\Mcp\TgOpsServer::class);
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/tg-outbound-daemon.php' => config_path('tg-outbound-daemon.php'),
            ], 'tg-outbound-daemon-config');
        }
    }
}
