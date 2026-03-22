<?php

declare(strict_types=1);

use BAGArt\TelegramBotManagement\Commands\TgBotManagerInit;

describe('TgBotManagerInit', function () {
    it('extracts bot_id from token', function () {
        $method = new ReflectionMethod(TgBotManagerInit::class, 'extractBotId');
        $method->setAccessible(true);
        $command = new TgBotManagerInit();

        expect($method->invoke($command, '123456:ABC-DEF_GHI'))->toBe('123456')
            ->and($method->invoke($command, '987654321:token_with_underscores'))->toBe('987654321');
    });
});
