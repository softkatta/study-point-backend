<?php

namespace App\Console\Commands;

use App\Services\BiometricLogSyncService;
use Illuminate\Console\Command;

class SyncBiometricLogs extends Command
{
    protected $signature = 'biometric:sync-logs {--minutes=15 : How many minutes of logs to import}';

    protected $description = 'Import SmartOffice device punches into attendance records';

    public function handle(BiometricLogSyncService $sync): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $result = $sync->syncRecentLogs($minutes);

        $this->info(sprintf(
            'Biometric logs synced: %d imported, %d skipped, %d errors (%d total punches).',
            $result['imported'],
            $result['skipped'],
            $result['errors'],
            $result['total'],
        ));

        return self::SUCCESS;
    }
}
