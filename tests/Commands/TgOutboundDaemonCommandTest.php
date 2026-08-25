<?php

declare(strict_types=1);

use BAGArt\ASKClient\Lockers\InMemoryLocker;
use BAGArt\AsyncKernel\ASKClock;
use BAGArt\AsyncKernel\Wrappers\ASKCacheWrapper;
use BAGArt\AsyncKernel\Wrappers\ASKLogWrapper;
use BAGArt\TelegramBot\Outbound\Adapters\InMemoryOutboundQueue;
use BAGArt\TelegramBot\Outbound\Adapters\KernelCacheAdapter;
use BAGArt\TelegramBot\Outbound\Config\OutboundWorkerConfig;
use BAGArt\TelegramBot\Outbound\OutboundCircuitBreaker;
use BAGArt\TelegramBot\Outbound\OutboundPipeline;
use BAGArt\TelegramBot\Outbound\TgOutboundDaemon;
use BAGArt\TelegramBot\Outbound\TgOutboundStats;
use BAGArt\TelegramBot\Outbound\LeaseRenewer;
use BAGArt\TelegramBotManagement\Commands\TgOutboundDaemonCommand;
use Psr\SimpleCache\CacheInterface;

beforeEach(function () {
    $clock = new ASKClock();
    $this->queue = new InMemoryOutboundQueue($clock);
    $cache = new KernelCacheAdapter(
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
    $this->stats = new TgOutboundStats($cache);
    $this->pipeline = new OutboundPipeline([]);
    $this->leaseRenewer = new LeaseRenewer($this->queue, $clock);
    $this->logger = new ASKLogWrapper();
    $this->config = new OutboundWorkerConfig();
    $this->cb = new OutboundCircuitBreaker($cache);

    $this->app->instance(\BAGArt\TelegramBot\Contracts\Outbound\OutboundQueueContract::class, $this->queue);
    $this->app->instance(TgOutboundStats::class, $this->stats);
});

/**
 * Daemon command runs a blocking AsyncKernel::run(), so handle()
 * is not called in the test. We test resolveDaemon() via a wrapper subclass.
 */
function makeDaemonResolverProxy(): object
{
    $proxy = new class () extends TgOutboundDaemonCommand {
        public function __construct()
        {
            // Without parent::__construct() — Laravel Command requires Container in constructor,
            // but we only need public access to resolveDaemon.
        }

        public function proxyResolve(string $mode, ASKLogWrapper $logger): TgOutboundDaemon
        {
            return $this->resolveDaemon($mode, $logger);
        }

        public function handle(ASKLogWrapper $logger): int
        {
            return self::SUCCESS;
        }
    };

    $definition = new \Symfony\Component\Console\Input\InputDefinition([
        new \Symfony\Component\Console\Input\InputOption('mode', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, '', 'single'),
        new \Symfony\Component\Console\Input\InputOption('redis-host', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, '', '127.0.0.1'),
        new \Symfony\Component\Console\Input\InputOption('redis-port', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, '', '6379'),
        new \Symfony\Component\Console\Input\InputOption('redis-timeout', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, '', '2.0'),
    ]);
    $input = new \Symfony\Component\Console\Input\ArrayInput([], $definition);
    $input->bind($definition);
    $proxy->setInput($input);

    return $proxy;
}

describe('TgOutboundDaemonCommand', function () {
    it('resolveDaemon builds a TgOutboundDaemon instance', function () {
        $proxy = makeDaemonResolverProxy();

        $daemon = $proxy->proxyResolve('single', $this->logger);

        expect($daemon)->toBeInstanceOf(TgOutboundDaemon::class);
    });
});
