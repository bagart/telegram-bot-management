<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot row of the tg_bots ↔ owners membership (unique per bot_id + user_id,
 * see the create_tg_bot_owners_table migration). Visibility of bots on the
 * web side is derived from this table.
 */
class TgBotOwner extends Model
{
    use HasTimestamps;

    protected $fillable = [
        'bot_id',
        'user_id',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(TgBot::class, 'bot_id', 'bot_id');
    }
}
