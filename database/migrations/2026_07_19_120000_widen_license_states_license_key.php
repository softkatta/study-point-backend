<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('license_states')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        // Encrypted cast stores ciphertext longer than a plain SK-… key (varchar 255 truncates).
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE license_states MODIFY license_key TEXT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE license_states ALTER COLUMN license_key TYPE TEXT');
        }
        // sqlite: affinity is flexible; no-op for existing installs
    }

    public function down(): void
    {
        if (! Schema::hasTable('license_states')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE license_states MODIFY license_key VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE license_states ALTER COLUMN license_key TYPE VARCHAR(255)');
        }
    }
};
