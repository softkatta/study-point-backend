<?php

namespace SoftKatta\Licensing\Console;

use Illuminate\Console\Command;
use SoftKatta\Licensing\Services\LicenseService;

class ActivateLicenseCommand extends Command
{
    protected $signature = 'license:activate {license_key : SoftKatta license key (e.g. SK-STUDY-...)}';

    protected $description = 'Re-activate this product against SoftKatta Company API after admin Activate';

    public function handle(LicenseService $license): int
    {
        $key = trim((string) $this->argument('license_key'));

        if ($key === '') {
            $this->error('License key is required.');

            return self::FAILURE;
        }

        $result = $license->activate($key);

        if (! ($result['ok'] ?? false)) {
            $this->error(($result['error_code'] ?? 'INVALID_LICENSE').': '.($result['message'] ?? 'Activation failed.'));

            return self::FAILURE;
        }

        $this->info('License activated.');
        $this->line('installation_id: '.($result['data']['installation_id'] ?? '—'));
        $this->line('bound_domain: '.($result['data']['bound_domain'] ?? '—'));

        return self::SUCCESS;
    }
}
