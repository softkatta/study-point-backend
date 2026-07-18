<?php

namespace SoftKatta\Licensing\Console;

use Illuminate\Console\Command;
use SoftKatta\Licensing\Services\LicenseService;

class VerifyLicenseCommand extends Command
{
    protected $signature = 'license:verify {--force : Force online verification}';

    protected $description = 'Verify product license with SoftKatta Company API';

    public function handle(LicenseService $license): int
    {
        if (! $license->isInstalled()) {
            $this->warn('Application is not installed.');

            return self::SUCCESS;
        }

        $result = $license->verify((bool) $this->option('force'));

        if ($result['ok'] ?? false) {
            $this->info('License valid'.(($result['from_cache'] ?? false) ? ' (cached/grace)' : '').'.');

            return self::SUCCESS;
        }

        $this->error(($result['error_code'] ?? 'ERROR').': '.($result['message'] ?? 'Verification failed'));

        return self::FAILURE;
    }
}
