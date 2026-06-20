<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'attendance_qr_token')) {
                $table->string('attendance_qr_token', 32)->nullable()->unique()->after('status');
            }
        });

        if (Schema::hasColumn('branches', 'attendance_qr_token')) {
            DB::table('branches')->whereNull('attendance_qr_token')->orderBy('id')->each(function ($row) {
                DB::table('branches')->where('id', $row->id)->update([
                    'attendance_qr_token' => strtoupper(Str::random(12)),
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'attendance_qr_token')) {
                $table->dropColumn('attendance_qr_token');
            }
        });
    }
};
