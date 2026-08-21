<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Commands\Processors;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\Contracts\Processing\Processors\TgTypeDTOProcessorContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiTypeDTOContract;
use BAGArt\TelegramBot\Exceptions\TgApiUserBreakException;
use BAGArt\TelegramBot\Processing\BotProcessorContext;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UpdateTypeDTO;
use BAGArt\TelegramBot\Wrappers\Wrappers\TgOutputWrapper;
use LogicException;

/**
 * Console long-polling demo processor behind tgbm:poll (--echo / --show / --once).
 * Instance-registered per command run — dependencies come from CLI options and
 * the DB-resolved bot token, so ::build() has no meaningful context-only
 * construction.
 */
final class BmPollerEchoUpdateProcessor implements TgTypeDTOProcessorContract
{
    public function __construct(
        private readonly TgBotApiDTOClientContract $dtoClient,
        private readonly TgOutputWrapper $output,
        private readonly TgBotConfig $botConfig,
        private readonly bool $echoMode,
        private readonly bool $showMode,
        private readonly bool $once,
        private readonly bool $isStrictOrdered = false,
    ) {
    }

    public static function build(BotProcessorContext $context): self
    {
        throw new LogicException(
            self::class.' is instance-registered (CLI-run dependencies); construct it directly.'
        );
    }

    public function support(
        TgApiTypeDTOContract $dto,
        TgBotConfig $botConfig,
        ?string $action = null,
    ): bool {
        return $dto instanceof UpdateTypeDTO;
    }

    public function isStrictOrdered(
        TgApiTypeDTOContract $dto,
        TgBotConfig $botConfig,
        ?string $action = null,
    ): bool {
        return $this->isStrictOrdered;
    }

    public function isNeedUpdateDTO(): bool
    {
        return false;
    }

    public function executionKey(TgApiTypeDTOContract $dto): ?string
    {
        return null;
    }

    public function process(
        TgApiTypeDTOContract $dto,
        TgBotConfig $botConfig,
        ?string $action = null,
    ): void {
        assert($dto instanceof UpdateTypeDTO);

        if ($this->showMode && $dto->message) {
            $this->output->line("{$dto->message->chat->id}: {$dto->message->text}");
        }

        if ($this->echoMode && $dto->message) {
            $sendMessageResponse = $this->dtoClient->request(
                botConfig: $this->botConfig,
                dto: new SendMessageMethodDTO(
                    chatId: $dto->message->chat->id,
                    text: "echo: {$dto->message->text}",
                ),
            );
            assert($sendMessageResponse->ok === true);
        }

        if ($this->once) {
            throw new TgApiUserBreakException('once');
        }
    }
}
