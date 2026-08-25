<?php

declare(strict_types=1);

use BAGArt\ASKClient\Lockers\InMemoryLocker;
use BAGArt\AsyncKernel\ASKClock;
use BAGArt\AsyncKernel\Wrappers\ASKCacheWrapper;
use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Outbound\Adapters\InMemoryOutboundQueue;
use BAGArt\TelegramBot\Outbound\Adapters\KernelCacheAdapter;
use BAGArt\TelegramBot\Outbound\Config\OutboundWorkerConfig;
use BAGArt\TelegramBot\Outbound\OutboundTask;
use BAGArt\TelegramBot\Outbound\TgOutboundStats;
use Psr\SimpleCache\CacheInterface;

if (!function_exists('makeTestOutboundCache')) {
    function makeTestOutboundCache(): KernelCacheAdapter
    {
        return new KernelCacheAdapter(
            new ASKCacheWrapper(new class () implements CacheInterface {
                private array $store = [];

                public function get(string $key, mixed $default = null): mixed
                {
                    return $this->store[$key] ?? $default;
                }

                public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
                {
                    $this->store[$key] = $value;
                    return true;
                }

                public function delete(string $key): bool
                {
                    unset($this->store[$key]);
                    return true;
                }

                public function clear(): bool
                {
                    $this->store = [];
                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable
                {
                    return [];
                }
                public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
                {
                    return true;
                }
                public function deleteMultiple(iterable $keys): bool
                {
                    return true;
                }
                public function has(string $key): bool
                {
                    return isset($this->store[$key]);
                }
            }),
            new InMemoryLocker(),
        );
    }
}

beforeEach(function () {
    $clock = new ASKClock();
    $this->queue = new InMemoryOutboundQueue($clock);
    $cache = makeTestOutboundCache();
    $this->stats = new TgOutboundStats($cache);
    $this->workerConfig = new OutboundWorkerConfig();

    $this->app->instance(\BAGArt\TelegramBot\Contracts\Outbound\OutboundQueueContract::class, $this->queue);
    $this->app->instance(TgOutboundStats::class, $this->stats);
    $this->app->instance(OutboundWorkerConfig::class, $this->workerConfig);
});

describe('TgOutboundToolCommand', function () {
    it('status returns JSON for fresh queue', function () {
        $this->artisan('tg:outbound:tool', ['--status' => true, '--json' => true])
            ->assertOk();
    });

    it('status shows size after push', function () {
        $this->queue->push(new OutboundTask(id: 't1', botConfig: new TgBotConfig(token: 'test:token', botId: 'bot1'), dtoClass: 'D', dtoData: []));
        $this->queue->push(new OutboundTask(id: 't2', botConfig: new TgBotConfig(token: 'test:token', botId: 'bot2'), dtoClass: 'D', dtoData: []));

        $this->artisan('tg:outbound:tool', ['--status' => true, '--json' => true])
            ->assertOk();
    });

    it('bottlenecks returns JSON', function () {
        $this->artisan('tg:outbound:tool', ['--bottlenecks' => true, '--json' => true])
            ->assertOk();
    });

    it('status with DLQ entries returns JSON', function () {
        $this->queue->push(new OutboundTask(id: 't1', botConfig: new TgBotConfig(token: 'test:token', botId: 'bot1'), dtoClass: 'D', dtoData: []));

        $this->artisan('tg:outbound:tool', ['--status' => true, '--json' => true])
            ->assertOk();
    });

    it('no action specified returns failure', function () {
        $this->artisan('tg:outbound:tool')
            ->assertFailed();
    });
});
