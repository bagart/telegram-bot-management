<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Exceptions;

/**
 * Thrown by TgModuleEnablementService::setSettings when the caller's
 * expectedVersion no longer matches the row's updatedAt (§13.4bis ladder).
 *
 * Carries the authoritative current payload so the controller maps it to
 * `409 conflict_stale` with `{values, version}` for the rebase dialog —
 * the client never needs a follow-up GET.
 *
 * Canonical owner is management-lib: the writer lives here and must not
 * depend on the menu module; the menu package references this class.
 */
final class StaleWriteException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $values authoritative stored settings
     * @param string $updatedAt version token (ISO-8601 UTC microseconds + Z);
     *                          empty string when the row vanished entirely
     */
    public function __construct(
        public readonly array $values,
        public readonly string $updatedAt,
    ) {
        parent::__construct('Settings were modified by someone else.');
    }
}
