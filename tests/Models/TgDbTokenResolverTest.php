<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Contracts\Outbound\BotTokenResolverContract;
use BAGArt\TelegramBotManagement\Models\TgDbTokenResolver;

describe('TgDbTokenResolver', function () {
    it('implements BotTokenResolverContract', function () {
        expect(TgDbTokenResolver::class)
            ->toImplement(BotTokenResolverContract::class);
    });

    it('is a readonly class', function () {
        $reflection = new ReflectionClass(TgDbTokenResolver::class);

        expect($reflection->isReadOnly())->toBeTrue()
            ->and($reflection->isFinal())->toBeTrue();
    });
});
