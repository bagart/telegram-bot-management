<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Commands;

class TgModuleDisableCommand extends TgModuleToggleCommand
{
    protected bool $enable = false;

    protected $signature = 'tg:module:disable
                            {module : Module id (see tg:modules:list)}
                            {--bot= : Bot id; omit for a platform-wide override}
                            {--chat= : Chat id; requires --bot, omit for the bot-level default}';
}
