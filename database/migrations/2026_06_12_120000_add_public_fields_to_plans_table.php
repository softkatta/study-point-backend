<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'badge')) {
                $table->string('badge', 80)->nullable()->after('description');
            }

            if (! Schema::hasColumn('plans', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('badge');
            }

            if (! Schema::hasColumn('plans', 'highlights')) {
                $table->json('highlights')->nullable()->after('is_featured');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('plans', 'badge')) {
                $columns[] = 'badge';
            }

            if (Schema::hasColumn('plans', 'is_featured')) {
                $columns[] = 'is_featured';
            }

            if (Schema::hasColumn('plans', 'highlights')) {
                $columns[] = 'highlights';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
