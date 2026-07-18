<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_states', function (Blueprint $table) {
            $table->id();
            $table->text('install_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('installation_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('product_slug')->nullable();
            $table->string('server_fingerprint')->nullable();
            $table->string('bound_domain')->nullable();
            $table->string('license_key')->nullable();
            $table->string('plan_slug')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->json('modules_cache')->nullable();
            $table->json('limits_cache')->nullable();
            $table->string('last_error_code')->nullable();
            $table->string('product_version_at_verify')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_states');
    }
};
