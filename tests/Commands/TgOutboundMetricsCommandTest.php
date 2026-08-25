<?php

declare(strict_types=1);

use BAGArt\ASKClient\Lockers\InMemoryLocker;
use BAGArt\AsyncKernel\ASKClock;
use BAGArt\AsyncKernel\Wrappers\ASKCacheWrapper;
use BAGArt\TelegramBot\Outbound\Adapters\InMemoryOutboundQueue;
use BAGArt\TelegramBot\Outbound\Adapters\KernelCacheAdapter;
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

    $this->app->instance(\BAGArt\TelegramBot\Contracts\Outbound\OutboundQueueContract::class, $this->queue);
    $this->app->instance(TgOutboundStats::class, $this->stats);
});

describe('TgOutboundMetricsCommand', function () {
    it('shows metrics with JSON output', function () {
        $this->artisan('tg:outbound:metrics', ['--json' => true])
            ->assertOk();
    });

    it('shows metrics with from/to hours', function () {
        $from = date('YmdH', time() - 7200);
        $to = date('YmdH');

        $this->artisan('tg:outbound:metrics', [
            '--from' => $from,
            '--to' => $to,
            '--json' => true,
        ])->assertOk();
    });

    it('shows metrics for a specific bot', function () {
        $this->artisan('tg:outbound:metrics', [
            '--bot-id' => 'bot1',
            '--json' => true,
        ])->assertOk();
    });
});
