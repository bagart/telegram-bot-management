<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Commands;

use BAGArt\TelegramBot\Modules\TgModuleRegistry;
use Illuminate\Console\Command;

class TgModulesListCommand extends Command
{
    protected $signature = 'tg:modules:list {--json : Structured JSON output}';

    protected $description = 'List discovered Telegram bot modules';

    public function handle(TgModuleRegistry $registry): int
    {
        $descriptors = $registry->all();

        if ($this->option('json')) {
            $this->line(json_encode(array_map(
                static fn ($d): array => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'version' => $d->version,
                    'capabilities' => array_map(static fn ($c) => $c->value, $d->capabilities),
                    'defaultEnabled' => $d->defaultEnabled,
                    'requiresModules' => $d->requiresModules,
                    'conflictsWith' => $d->conflictsWith,
                ],
                $descriptors,
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($descriptors === []) {
            $this->info('No modules discovered.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'name', 'version', 'capabilities', 'default enabled'],
            array_map(
                static fn ($d): array => [
                    $d->id,
                    $d->name,
                    $d->version,
                    implode(', ', array_map(static fn ($c) => $c->value, $d->capabilities)),
                    $d->defaultEnabled ? 'yes' : 'no',
                ],
                $descriptors,
            ),
        );

        return self::SUCCESS;
    }
}
