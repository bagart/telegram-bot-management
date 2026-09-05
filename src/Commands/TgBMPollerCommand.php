<?php

declare(strict_types=1);

namespace BAGArt\TelegramBotManagement\Commands;

use BAGArt\AsyncKernel\AsyncKernel;
use BAGArt\AsyncKernel\Drivers\ASKFiberScheduler;
use BAGArt\AsyncKernel\Wrappers\ASKLogWrapper;
use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Configs\TgPollerConfig;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiClientContract;
use BAGArt\TelegramBot\Contracts\ApiCommunication\TgBotApiDTOClientContract;
use BAGArt\TelegramBot\Wrappers\Wrappers\TgOutputWrapper;
use BAGArt\TelegramBotBasic\Commands\Traits\LongPollingCommandTrait;
use BAGArt\TelegramBotManagement\Commands\Processors\BmPollerEchoUpdateProcessor;
use BAGArt\TelegramBotManagement\Models\TgBot;
use Illuminate\Console\Command;

class TgBMPollerCommand extends Command
{
    use LongPollingCommandTrait;

    protected $signature = 'tgbm:poll
                            {bot_uuid : TgBot UUID from database}
                            {--echo       : ECHO-mode(ping-pong)}
                            {--show       : Show messages}
                            {--timeout=30 : Long-polling server timeout in seconds}
                            {--limit=100  : Maximum updates per request (1–100)}
                            {--once       : Process one batch of updates and exit}';

    protected $description = 'Start the Telegram bot in long-polling mode';

    public function handle(
        TgBotApiDTOClientContract $tgDTOClient,
        TgBotApiClientContract $client,
        ASKLogWrapper $logger,
    ): int {
        $botUuid = $this->argument('bot_uuid');
        $tgBot = TgBot::find($botUuid);
        if ($tgBot === null) {
            $this->error("Bot not found: {$botUuid}");

            return self::FAILURE;
        }

        $token = $tgBot->token;
        $timeout = (int) $this->option('timeout');
        $once = $this->option('once');
        $echoMode = $this->option('echo');
        $showMode = $this->option('show');

        $asyncKernel = new AsyncKernel(logger: $logger);
        $asyncKernel->addTickable(new ASKFiberScheduler());

        $configPoller = $this->buildConfigPoller(
            token: $token,
            updateProcessor: new BmPollerEchoUpdateProcessor(
                dtoClient: $tgDTOClient,
                output: new TgOutputWrapper($this->output),
                botConfig: new TgBotConfig(token: $token),
                echoMode: $echoMode,
                showMode: $showMode,
                once: $once,
            ),
            logger: $logger,
            pollerConfig: new TgPollerConfig(
                timeout: $timeout,
            ),
        );

        $asyncKernel->addDaemon($configPoller);

        // TgBotSetup no longer carries daemons (readonly DTOs only) — extra
        // daemons are built explicitly by their own commands.
        $asyncKernel->run();

        return self::SUCCESS;
    }
}
