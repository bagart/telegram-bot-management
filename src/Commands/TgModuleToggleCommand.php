<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Commands;

use BAGArt\TelegramBot\Modules\TgModuleRegistry;
use BAGArt\TelegramBotManagement\Services\TgModuleEnablementService;
use Illuminate\Console\Command;

/**
 * CLI toggles ride the guarded writer API (single-writer rule, §8.6) —
 * platform-level scope included via $botId = null.
 */
abstract class TgModuleToggleCommand extends Command
{
    protected bool $enable;

    public function handle(TgModuleEnablementService $enablement, TgModuleRegistry $registry): int
    {
        $moduleId = (string)$this->argument('module');
        $botId = $this->option('bot');
        $chatId = $this->option('chat') !== null ? (int)$this->option('chat') : null;

        if (!is_string($botId) || $botId === '') {
            $botId = null;
        }

        if (!$registry->has($moduleId)) {
            $this->error("Module '{$moduleId}' is not discovered. See tg:modules:list.");

            return self::FAILURE;
        }

        if ($botId === null && $chatId !== null) {
            $this->error('--chat requires --bot.');

            return self::FAILURE;
        }

        $enablement->setEnabled($moduleId, $botId, $chatId, $this->enable);

        $scope = $this->describeScope($botId, $chatId);
        $this->info(($this->enable ? 'Enabled' : 'Disabled')." module '{$moduleId}' {$scope}.");

        return self::SUCCESS;
    }

    private function describeScope(?string $botId, ?int $chatId): string
    {
        if ($botId === null) {
            return 'platform-wide (bot_id NULL)';
        }

        return $chatId !== null
            ? "for bot '{$botId}' chat {$chatId}"
            : "for bot '{$botId}' (all chats)";
    }
}
