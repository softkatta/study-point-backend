<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            if (! Schema::hasColumn('admissions', 'notify_parent_email')) {
                $table->boolean('notify_parent_email')->default(false)->after('notify_whatsapp');
            }
            if (! Schema::hasColumn('admissions', 'notify_parent_whatsapp')) {
                $table->boolean('notify_parent_whatsapp')->default(false)->after('notify_parent_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            if (Schema::hasColumn('admissions', 'notify_parent_whatsapp')) {
                $table->dropColumn('notify_parent_whatsapp');
            }
            if (Schema::hasColumn('admissions', 'notify_parent_email')) {
                $table->dropColumn('notify_parent_email');
            }
        });
    }
};
