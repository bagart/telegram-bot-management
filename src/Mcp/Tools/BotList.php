<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Mcp\Tools;

use BAGArt\TelegramBotManagement\Models\TgBot;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * List registered bots. Token and secret_token are hidden on the model, so the
 * response never exposes credentials.
 */
#[IsReadOnly]
class BotList extends Tool
{
    protected string $description = 'List the bot_ids registered in the tg_bots table. Tokens and secret tokens are never exposed (hidden on the model). Use this to enumerate which bots are configured before per-bot operations.';

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        $bots = TgBot::all(['bot_id']);

        return Response::json([
            'bots' => $bots->map(fn (TgBot $bot): array => [
                'bot_id' => $bot->bot_id,
            ])->values()->all(),
            'count' => $bots->count(),
        ]);
    }
}
