<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Commands;

use BAGArt\ASKClient\HttpClient\PoolWarmer;
use BAGArt\ASKClient\SocketClient\AskHttpSocketClient;
use BAGArt\ASKClientRedis\Redis\RedisDsn;
use BAGArt\AsyncKernel\ASKClock;
use BAGArt\AsyncKernel\AsyncKernel;
use BAGArt\AsyncKernel\Drivers\ASKFiberScheduler;
use BAGArt\AsyncKernel\Wrappers\ASKLogWrapper;
use BAGArt\TelegramBot\Configs\TgServiceConfig;
use BAGArt\TelegramBot\Outbound\Config\OutboundWorkerConfig;
use BAGArt\TelegramBot\Outbound\TgOutboundDaemon;
use BAGArt\TelegramBot\TgBotSetupFactory;
use Illuminate\Console\Command;

class TgOutboundDaemonCommand extends Command
{
    protected $signature = 'tgbm:outbound-daemon
                            {--mode=single : Daemon mode (single|multi)}
                            {--redis-host=127.0.0.1 : Redis host}
                            {--redis-port=6379 : Redis port}
                            {--redis-timeout=2.0 : Redis connection timeout}
                            {--memory-limit=256M : PHP memory limit for daemon}
                            {--warm-connections= : Override config warm_connections}
                            {--debug : Enable debug logging}';

    protected $description = 'Start the Telegram outbound worker daemon';

    public function handle(
        ASKLogWrapper $logger,
    ): int {
        $this->configureEnvironment();

        $this->line('Starting OutboundWorker daemon...');

        $mode = (string)$this->option('mode');
        $daemon = $this->resolveDaemon($mode, $logger);

        $kernel = new AsyncKernel(logger: $logger);
        $kernel->addDaemon($daemon);

        $this->warmSocketPool($kernel, $logger);

        $kernel->run();

        return self::SUCCESS;
    }

    protected function resolveDaemon(string $mode, ASKLogWrapper $logger): TgOutboundDaemon
    {
        $serviceConfig = new TgServiceConfig();
        $workerConfig = new OutboundWorkerConfig();
        $clock = new ASKClock();
        $scheduler = new ASKFiberScheduler();

        if ($mode === 'multi') {
            $redisHost = (string)$this->option('redis-host');
            $serviceConfig->outboundQueueStore = 'redis';
            $serviceConfig->redisDsn = (new RedisDsn(
                host: $redisHost !== '' ? $redisHost : (string)config('database.redis.default.host', '127.0.0.1'),
                port: (int)$this->option('redis-port') ?: (int)config('database.redis.default.port', 6379),
                timeout: (float)$this->option('redis-timeout') ?: 2.0,
            ))->toString();
        }

        /** @var TgBotSetupFactory $factory */
        $factory = app(TgBotSetupFactory::class);

        $parts = $factory->createOutboundDaemonParts(serviceConfig: $serviceConfig, workerConfig: $workerConfig);

        return new TgOutboundDaemon(
            queue: $parts['queue'],
            pipeline: $parts['pipeline'],
            circuitBreaker: $parts['circuitBreaker'],
            stats: $parts['stats'],
            leaseRenewer: $parts['leaseRenewer'],
            logger: $logger,
            config: $workerConfig,
            scheduler: $scheduler,
        );
    }

    private function configureEnvironment(): void
    {
        $memoryLimit = (string)$this->option('memory-limit');

        ini_set('memory_limit', $memoryLimit);

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }

        if ($this->option('debug')) {
            $this->line('Debug mode enabled');
        }
    }

    private function warmSocketPool(AsyncKernel $kernel, ASKLogWrapper $logger): void
    {
        $pool = (array)config('tg-outbound-daemon.daemon.socket_pool', []);

        if (!($pool['enabled'] ?? false)) {
            return;
        }

        $warmCount = $this->option('warm-connections') !== null
            ? (int)$this->option('warm-connections')
            : (int)($pool['warm_connections'] ?? 4);

        $warmHost = (string)($pool['warm_host'] ?? 'api.telegram.org');

        // Initial one-time warm-up so the first requests pay no handshake cost.
        if ($warmCount > 0) {
            $client = app(AskHttpSocketClient::class);
            $warmed = $client->warmUp($warmHost, $warmCount);
            $logger->info('TgOutboundDaemon socket pool warmed', [
                'host' => $warmHost,
                'warmed' => $warmed,
            ]);
            $this->info("Socket pool warmed ({$warmed} connections).");
        }

        // Register the periodic warmer in the kernel tick loop.
        $warmer = app(PoolWarmer::class);
        if ($warmer !== null) {
            $kernel->addTickable($warmer);
        }
    }
}
