<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Services;

use BAGArt\AsyncKernel\Wrappers\ASKCacheWrapper;
use BAGArt\AsyncKernel\Wrappers\ASKLogWrapper;
use BAGArt\TelegramBot\Contracts\Modules\ModuleEnablementContract;
use BAGArt\TelegramBot\Contracts\Modules\ModuleSettingsContract;
use BAGArt\TelegramBot\Modules\TgModuleRegistry;
use BAGArt\TelegramBotManagement\Models\TgModuleEnablement;
use Throwable;

/**
 * Resolves module enablement with the inheritance chain
 * chat override → bot default → platform row → descriptor()->defaultEnabled.
 *
 * Steady-state webhooks hit only the cache (NFR-5: 0 SQL); SQL happens once
 * per (bot, chat) after TTL expiry or refresh().
 */
class TgModuleEnablementService implements ModuleEnablementContract, ModuleSettingsContract
{
    private const CACHE_PREFIX = 'tg.mod.enable.';
    private const SETTINGS_CACHE_PREFIX = 'tg.mod.settings.';

    /** @var array<string, array<string, bool>> in-memory map cacheKey => [moduleId => bool] */
    private array $memory = [];

    /** @var array<string, array<string, array<string, mixed>>> in-memory settings map */
    private array $settingsMemory = [];

    public function __construct(
        private readonly TgModuleRegistry $moduleRegistry,
        private readonly ASKCacheWrapper $cache,
        private readonly int $ttlSeconds = 300,
        private readonly ?ASKLogWrapper $logger = null,
    ) {
    }

    public function isEnabled(string $moduleId, string $botId, int $chatId): bool
    {
        try {
            $decisions = $this->decisions($botId, $chatId);
        } catch (Throwable $e) {
            return $this->onEnablementStorageError($moduleId, $botId, $chatId, $e);
        }

        return $decisions[$moduleId]
            ?? (bool)($this->moduleRegistry->defaultEnabledOf($moduleId) ?? false);
    }

    /**
     * Fail policy on enablement-storage DB errors (Q-D2): fail-closed modules
     * are treated as disabled; the rest fall back to the descriptor default
     * (fail-open). The error is logged and nothing is cached, so recovery is
     * immediate once the storage is back.
     */
    private function onEnablementStorageError(
        string $moduleId,
        string $botId,
        int $chatId,
        Throwable $e,
    ): bool {
        $this->logger?->error('Module enablement storage error, applying fail policy', [
            'moduleId' => $moduleId,
            'botId' => $botId,
            'chatId' => $chatId,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);

        if ($this->moduleRegistry->failClosedOf($moduleId) === true) {
            return false;
        }

        return (bool)($this->moduleRegistry->defaultEnabledOf($moduleId) ?? false);
    }

    public function refresh(string $botId, ?int $chatId = null): void
    {
        if ($chatId !== null) {
            unset($this->memory[$this->cacheKey($botId, $chatId)]);
            $this->cache->delete($this->cacheKey($botId, $chatId));
            unset($this->settingsMemory[$this->settingsCacheKey($botId, $chatId)]);
            $this->cache->delete($this->settingsCacheKey($botId, $chatId));

            return;
        }

        // Bot/platform-level toggle: exact cached (bot, *) keys cannot be
        // enumerated via PSR-16, so only in-memory entries are dropped here;
        // cross-process staleness is bounded by the TTL (risk R-8).
        foreach (array_keys($this->memory) as $key) {
            if (str_starts_with($key, self::CACHE_PREFIX.$botId.'.')) {
                unset($this->memory[$key]);
            }
        }

        foreach (array_keys($this->settingsMemory) as $key) {
            if (str_starts_with($key, self::SETTINGS_CACHE_PREFIX.$botId.'.')) {
                unset($this->settingsMemory[$key]);
            }
        }
    }

    /**
     * Effective settings of a module in (bot, chat) scope, merged through the
     * same inheritance chain as enablement: platform → bot → chat (more
     * specific keys win). Cached with the same TTL policy.
     *
     * @return array<string, mixed>
     */
    public function settingsFor(string $moduleId, string $botId, int $chatId): array
    {
        return $this->settingsMap($botId, $chatId)[$moduleId] ?? [];
    }

    /**
     * @return array<string, array<string, mixed>> moduleId => effective settings
     */
    private function settingsMap(string $botId, int $chatId): array
    {
        $cacheKey = $this->settingsCacheKey($botId, $chatId);

        if (isset($this->settingsMemory[$cacheKey])) {
            return $this->settingsMemory[$cacheKey];
        }

        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return $this->settingsMemory[$cacheKey] = $cached;
        }

        $rows = TgModuleEnablement::query()
            ->where(function ($query) use ($botId, $chatId): void {
                $query->where(fn ($q) => $q->where('bot_id', $botId)->where('chat_id', $chatId))
                    ->orWhere(fn ($q) => $q->where('bot_id', $botId)->whereNull('chat_id'))
                    ->orWhere(fn ($q) => $q->whereNull('bot_id'));
            })
            ->whereNotNull('module_settings')
            ->get(['bot_id', 'chat_id', 'module_id', 'module_settings']);

        $map = [];
        // Least specific first, so more specific keys overwrite
        foreach (['platform', 'bot', 'chat'] as $level) {
            foreach ($rows as $row) {
                if ($this->rowLevel($row) === $level && is_array($row->module_settings)) {
                    $map[$row->module_id] = array_merge(
                        $map[$row->module_id] ?? [],
                        $row->module_settings,
                    );
                }
            }
        }

        $this->cache->set($cacheKey, $map, $this->ttlSeconds);

        return $this->settingsMemory[$cacheKey] = $map;
    }

    /**
     * Effective decision map for (bot, chat): explicit rows at any level,
     * falling back to descriptor defaults for the rest.
     *
     * @return array<string, bool>
     */
    private function decisions(string $botId, int $chatId): array
    {
        $cacheKey = $this->cacheKey($botId, $chatId);

        if (isset($this->memory[$cacheKey])) {
            return $this->memory[$cacheKey];
        }

        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return $this->memory[$cacheKey] = $cached;
        }

        $rows = TgModuleEnablement::query()
            ->where(function ($query) use ($botId, $chatId): void {
                $query->where(fn ($q) => $q->where('bot_id', $botId)->where('chat_id', $chatId))
                    ->orWhere(fn ($q) => $q->where('bot_id', $botId)->whereNull('chat_id'))
                    ->orWhere(fn ($q) => $q->whereNull('bot_id'));
            })
            ->get(['bot_id', 'chat_id', 'module_id', 'is_enabled']);

        $map = [];
        // Least specific first, so more specific rows overwrite
        foreach (['platform', 'bot', 'chat'] as $level) {
            foreach ($rows as $row) {
                if ($this->rowLevel($row) === $level) {
                    $map[$row->module_id] = $row->is_enabled;
                }
            }
        }

        $this->cache->set($cacheKey, $map, $this->ttlSeconds);

        return $this->memory[$cacheKey] = $map;
    }

    private function rowLevel(TgModuleEnablement $row): string
    {
        if ($row->bot_id === null) {
            return 'platform';
        }

        return $row->chat_id === null ? 'bot' : 'chat';
    }

    private function cacheKey(string $botId, int $chatId): string
    {
        return self::CACHE_PREFIX.$botId.'.'.$chatId;
    }

    private function settingsCacheKey(string $botId, int $chatId): string
    {
        return self::SETTINGS_CACHE_PREFIX.$botId.'.'.$chatId;
    }
}
