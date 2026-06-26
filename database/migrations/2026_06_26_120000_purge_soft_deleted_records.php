<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables that previously used Laravel SoftDeletes. */
    private array $tables = [
        'plans',
        'expenses',
        'admissions',
        'biometric_devices',
        'branches',
        'subscriptions',
        'students',
        'users',
        'invoices',
        'payments',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            DB::table($table)->whereNotNull('deleted_at')->delete();
        }
    }

    public function down(): void
    {
        // Purged rows cannot be restored.
    }
};
