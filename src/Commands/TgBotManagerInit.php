<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Commands;

use BAGArt\TelegramBotBasic\Commands\Traits\TokenResolverTrait;
use BAGArt\TelegramBotManagement\Models\TgBot;
use Illuminate\Console\Command;

class TgBotManagerInit extends Command
{
    use TokenResolverTrait;

    protected $signature = 'tgbm:init
                            {--token=     : Telegram Bot token}
                            {--user_id= : Telegram original User ID (optional)}';

    protected $description = 'Telegram Bot Manager Setup';

    public function handle(): int
    {
        $token = $this->resolveToken();
        if ($token === null) {
            return self::FAILURE;
        }

        $botId = $this->extractBotId($token);
        $userId = $this->option('user_id');

        if (TgBot::where('bot_id', $botId)->exists()) {
            $this->warn("Bot {$botId} already exists. Skipping creation.");

            return static::SUCCESS;
        }

        $tgBot = TgBot::create([
            'bot_id' => $botId,
            'token' => $token,
        ]);

        $this->info("Telegram Bot added: bot_id: {$tgBot->bot_id}; owner(user_id): ".($userId ?: 'EMPTY'));

        return static::SUCCESS;
    }

    private function extractBotId(string $token): string
    {
        return (string) strstr($token, ':', true);
    }
}
