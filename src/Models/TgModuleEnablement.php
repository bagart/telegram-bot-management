<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (bot, chat, module) enablement decision.
 *
 * Inheritance chain: chat row (bot_id + chat_id) → bot default (chat_id NULL)
 * → platform override (bot_id NULL) → descriptor()->defaultEnabled.
 * Absence of a row means "inherited" (tri-state).
 *
 * @property string $id
 * @property string|null $bot_id
 * @property int|null $chat_id
 * @property string $module_id
 * @property bool $is_enabled
 * @property array|null $module_settings
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class TgModuleEnablement extends Model
{
    use HasFactory;
    use HasTimestamps;
    use HasUuids;

    protected $fillable = [
        'bot_id',
        'chat_id',
        'module_id',
        'is_enabled',
        'module_settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'chat_id' => 'integer',
        'module_settings' => 'array',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(TgBot::class, 'bot_id', 'bot_id');
    }

    /** Factory lives in the root app (Database\Factories), not inside the lib. */
    public static function newFactory(): Factory
    {
        return \Database\Factories\TgModuleEnablementFactory::new();
    }
}
