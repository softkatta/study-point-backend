<?php

namespace App\Console\Commands;

use App\Services\AuditService;
use Illuminate\Console\Command;

class PurgeAuditLogs extends Command
{
    protected $signature = 'audit:purge';

    protected $description = 'Delete audit logs older than the configured retention period';

    public function handle(AuditService $audit): int
    {
        $deleted = $audit->purgeExpired();
        $this->info("Purged {$deleted} audit log(s).");

        return self::SUCCESS;
    }
}
