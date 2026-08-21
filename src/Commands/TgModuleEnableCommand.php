<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Commands;

class TgModuleEnableCommand extends TgModuleToggleCommand
{
    protected bool $enable = true;

    protected $signature = 'tg:module:enable
                            {module : Module id (see tg:modules:list)}
                            {--bot= : Bot id; omit for a platform-wide override}
                            {--chat= : Chat id; requires --bot, omit for the bot-level default}';
}
