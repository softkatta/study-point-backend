<?php

namespace SoftKatta\Licensing\Console;

use Illuminate\Console\Command;
use SoftKatta\Licensing\Services\LicenseService;

class HeartbeatLicenseCommand extends Command
{
    protected $signature = 'license:heartbeat';

    protected $description = 'Send SoftKatta license heartbeat to Company API';

    public function handle(LicenseService $license): int
    {
        if (! $license->isInstalled()) {
            $this->warn('Application is not installed.');

            return self::SUCCESS;
        }

        $result = $license->heartbeat();

        if ($result['ok'] ?? false) {
            $this->info('Heartbeat ok'.(($result['grace'] ?? false) ? ' (grace)' : '').'.');

            return self::SUCCESS;
        }

        $this->error(($result['error_code'] ?? 'ERROR').': '.($result['message'] ?? 'Heartbeat failed'));

        return self::FAILURE;
    }
}
