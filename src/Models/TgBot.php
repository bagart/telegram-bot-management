<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $bot_id
 * @property string $token
 * @property string|null $secret_token
 */
class TgBot extends Model
{
    use HasTimestamps;

    protected $primaryKey = 'bot_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'bot_id',
        'token',
        'secret_token',
    ];

    protected $hidden = [
        'token',
        'secret_token',
    ];

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(TgBotOwner::class, 'tg_bot_owners', 'bot_id', 'user_id');
    }

    public function moduleEnablements(): HasMany
    {
        return $this->hasMany(TgModuleEnablement::class, 'bot_id', 'bot_id');
    }
}
