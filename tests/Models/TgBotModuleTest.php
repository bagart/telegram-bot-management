<?php

declare(strict_types=1);

use BAGArt\TelegramBotManagement\Models\TgBotModule;

describe('TgBotModule model', function () {
    it('uses auto-incrementing primary key', function () {
        $model = new TgBotModule();

        expect($model->getKeyName())->toBe('id')
            ->and($model->getIncrementing())->toBeTrue();
    });

    it('has correct fillable attributes', function () {
        $model = new TgBotModule();

        expect($model->getFillable())->toBe([
            'bot_id',
            'chat_id',
            'message_thread_id',
        ]);
    });

    it('has bot relation', function () {
        $model = new TgBotModule();

        expect($model->bot())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    });
});
