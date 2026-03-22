<?php

declare(strict_types=1);

use BAGArt\TelegramBotManagement\Models\TgBotOwner;

describe('TgBotOwner model', function () {
    it('uses auto-incrementing primary key', function () {
        $model = new TgBotOwner();

        expect($model->getKeyName())->toBe('id')
            ->and($model->getIncrementing())->toBeTrue();
    });

    it('has correct fillable attributes', function () {
        $model = new TgBotOwner();

        expect($model->getFillable())->toBe([
            'bot_id',
            'user_id',
        ]);
    });

    it('has bot relation', function () {
        $model = new TgBotOwner();

        expect($model->bot())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    });
});
