<?php

namespace SoftKatta\Licensing\Console;

use Illuminate\Console\Command;
use SoftKatta\Licensing\Services\LicenseService;

class RefreshLicenseTokenCommand extends Command
{
    protected $signature = 'license:refresh-token';

    protected $description = 'Rotate SoftKatta install token via Company API';

    public function handle(LicenseService $license): int
    {
        if (! $license->isInstalled()) {
            $this->warn('Application is not installed.');

            return self::SUCCESS;
        }

        $result = $license->refreshToken();

        if ($result['ok'] ?? false) {
            $this->info('Install token refreshed.');

            return self::SUCCESS;
        }

        $this->error(($result['error_code'] ?? 'ERROR').': '.($result['message'] ?? 'Refresh failed'));

        return self::FAILURE;
    }
}
