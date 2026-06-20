<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('timezone', 60)->nullable()->after('website');
            $table->string('currency', 10)->nullable()->after('timezone');
            $table->string('currency_symbol', 5)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'currency', 'currency_symbol']);
        });
    }
};
