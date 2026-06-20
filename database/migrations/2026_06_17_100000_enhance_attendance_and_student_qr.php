<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'plan_id')) {
                $table->foreignId('plan_id')->nullable()->after('branch_id')->constrained('plans')->nullOnDelete();
            }
            if (! Schema::hasColumn('students', 'qr_token')) {
                $table->string('qr_token', 32)->nullable()->unique()->after('verify_token');
            }
        });

        if (Schema::hasColumn('students', 'qr_token')) {
            DB::table('students')->whereNull('qr_token')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('students')->where('id', $row->id)->update([
                        'qr_token' => $row->verify_token ?: strtoupper(substr(md5((string) $row->id), 0, 10)),
                    ]);
                }
            });
        }

        Schema::table('attendance_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_logs', 'attendance_date')) {
                $table->date('attendance_date')->nullable()->after('branch_id');
            }
            if (! Schema::hasColumn('attendance_logs', 'marked_by_user_id')) {
                $table->foreignId('marked_by_user_id')->nullable()->after('source')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('attendance_logs', 'marked_by_role')) {
                $table->string('marked_by_role', 40)->nullable()->after('marked_by_user_id');
            }
        });

        DB::table('attendance_logs')
            ->whereNull('attendance_date')
            ->whereNotNull('check_in')
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('attendance_logs')->where('id', $row->id)->update([
                    'attendance_date' => date('Y-m-d', strtotime($row->check_in)),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_logs', 'marked_by_role')) {
                $table->dropColumn('marked_by_role');
            }
            if (Schema::hasColumn('attendance_logs', 'marked_by_user_id')) {
                $table->dropConstrainedForeignId('marked_by_user_id');
            }
            if (Schema::hasColumn('attendance_logs', 'attendance_date')) {
                $table->dropColumn('attendance_date');
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'qr_token')) {
                $table->dropColumn('qr_token');
            }
            if (Schema::hasColumn('students', 'plan_id')) {
                $table->dropConstrainedForeignId('plan_id');
            }
        });
    }
};
