<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('is_head_office')->default(false)->after('status');
            $table->string('email', 150)->nullable()->after('manager_phone');
            $table->string('opens_at', 30)->nullable()->after('operating_hours');
            $table->string('closes_at', 30)->nullable()->after('opens_at');
            $table->string('social_facebook', 300)->nullable()->after('closes_at');
            $table->string('social_instagram', 300)->nullable()->after('social_facebook');
            $table->string('social_twitter', 300)->nullable()->after('social_instagram');
            $table->string('social_youtube', 300)->nullable()->after('social_twitter');
            $table->index('is_head_office');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex(['is_head_office']);
            $table->dropColumn([
                'is_head_office',
                'email',
                'opens_at',
                'closes_at',
                'social_facebook',
                'social_instagram',
                'social_twitter',
                'social_youtube',
            ]);
        });
    }
};
