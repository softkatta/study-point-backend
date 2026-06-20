<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('operating_hours', 100)->nullable()->after('address');
            $table->json('features')->nullable()->after('operating_hours');
            $table->boolean('is_accepting_admissions')->default(true)->after('features');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['operating_hours', 'features', 'is_accepting_admissions']);
        });
    }
};
