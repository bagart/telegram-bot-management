<?php

declare(strict_types=1);

use BAGArt\TelegramBotManagement\Models\TgBot;

describe('TgBot model', function () {
    it('uses bot_id as primary key', function () {
        $model = new TgBot();

        expect($model->getKeyName())->toBe('bot_id');
    });

    it('is non-incrementing', function () {
        $model = new TgBot();

        expect($model->getIncrementing())->toBeFalse();
    });

    it('has string key type', function () {
        $model = new TgBot();

        expect($model->getKeyType())->toBe('string');
    });

    it('has correct fillable attributes', function () {
        $model = new TgBot();

        expect($model->getFillable())->toBe([
            'bot_id',
            'token',
            'secret_token',
        ]);
    });

    it('hides token and secret_token', function () {
        $model = new TgBot();

        expect($model->getHidden())->toBe([
            'token',
            'secret_token',
        ]);
    });
});
