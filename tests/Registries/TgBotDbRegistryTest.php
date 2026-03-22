<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Contracts\BotServices\TgBotRegistryContract;
use BAGArt\TelegramBot\Exceptions\TgTechnicalWithEntityException;
use BAGArt\TelegramBot\TgIntegration\BotSecretDTO;
use BAGArt\TelegramBotManagement\Registries\TgBotDbRegistry;

describe('TgBotDbRegistry', function () {
    it('implements TgBotRegistryContract', function () {
        expect(TgBotDbRegistry::class)
            ->toImplement(TgBotRegistryContract::class);
    });

    it('register() throws TgTechnicalWithEntityException', function () {
        $registry = TgBotDbRegistry::build();

        $registry->register(new BotSecretDTO('123456:ABC-DEF'));
    })->throws(TgTechnicalWithEntityException::class, 'Use TelegramBotManager to register new Bots');

    it('build() returns a TgBotDbRegistry instance', function () {
        expect(TgBotDbRegistry::build())->toBeInstanceOf(TgBotDbRegistry::class);
    });
});
