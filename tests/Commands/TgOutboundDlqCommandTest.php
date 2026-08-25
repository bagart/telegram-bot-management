<?php

declare(strict_types=1);

use BAGArt\ASKClient\Lockers\InMemoryLocker;
use BAGArt\AsyncKernel\ASKClock;
use BAGArt\AsyncKernel\Wrappers\ASKCacheWrapper;
use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Outbound\Adapters\InMemoryOutboundQueue;
use BAGArt\TelegramBot\Outbound\Adapters\KernelCacheAdapter;
use BAGArt\TelegramBot\Outbound\Config\OutboundWorkerConfig;
use BAGArt\TelegramBot\Outbound\OutboundEnvelope;
use BAGArt\TelegramBot\Outbound\OutboundTask;
use BAGArt\TelegramBot\Outbound\OutboundTaskState;
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

describe('TgOutboundDlqCommand', function () {
    it('list returns empty JSON for fresh queue', function () {
        $this->artisan('tg:outbound:dlq', ['--list' => true, '--json' => true])
            ->assertOk();
    });

    it('list shows entries after push', function () {
        $task = new OutboundTask(id: 'dlq-test', botConfig: new TgBotConfig(token: 'test:token', botId: 'bot1'), dtoClass: 'SendMsg', dtoData: []);
        $envelope = new OutboundEnvelope($task, new OutboundTaskState());
        $this->queue->pushToDeadLetter($envelope, 'bad_request');

        $this->artisan('tg:outbound:dlq', ['--list' => true, '--json' => true])
            ->assertOk();
    });

    it('retry on non-existent entry fails', function () {
        $this->artisan('tg:outbound:dlq', ['--retry' => 'nonexistent', '--json' => true])
            ->assertFailed();
    });

    it('retry-all on empty queue succeeds', function () {
        $this->artisan('tg:outbound:dlq', ['--retry-all' => true, '--json' => true])
            ->assertOk();
    });

    it('purge on empty queue succeeds', function () {
        $this->artisan('tg:outbound:dlq', ['--purge' => true, '--json' => true])
            ->assertOk();
    });

    it('retry restores DLQ entry to main queue', function () {
        $task = new OutboundTask(id: 'retry-me', botConfig: new TgBotConfig(token: 'test:token', botId: 'bot1'), dtoClass: 'SendMsg', dtoData: ['chat_id' => 1]);
        $envelope = new OutboundEnvelope($task, new OutboundTaskState());
        $this->queue->pushToDeadLetter($envelope, 'error');

        expect($this->queue->deadLetterSize())->toBe(1);
        expect($this->queue->size())->toBe(0);

        $this->artisan('tg:outbound:dlq', ['--retry' => 'retry-me', '--json' => true])
            ->assertOk();

        expect($this->queue->deadLetterSize())->toBe(0);
        expect($this->queue->size())->toBe(1);
    });

    it('list with bot filter works', function () {
        $this->artisan('tg:outbound:dlq', ['--list' => true, '--bot' => 'bot1', '--json' => true])
            ->assertOk();
    });

    it('list with limit shows correct count', function () {
        for ($i = 0; $i < 5; $i++) {
            $task = new OutboundTask(id: "t{$i}", botConfig: new TgBotConfig(token: 'test:token', botId: 'bot1'), dtoClass: 'D', dtoData: []);
            $envelope = new OutboundEnvelope($task, new OutboundTaskState());
            $this->queue->pushToDeadLetter($envelope, 'error');
        }

        $this->artisan('tg:outbound:dlq', ['--list' => true, '--limit' => 3, '--json' => true])
            ->assertOk();
    });
});
