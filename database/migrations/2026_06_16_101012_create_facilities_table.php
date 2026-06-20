<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('title', 120);
            $table->string('short_description', 255)->nullable();
            $table->text('description')->nullable();
            $table->json('bullet_points')->nullable();
            $table->string('icon', 40)->default('sparkles');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('show_in_nav')->default(true);
            $table->boolean('show_on_home')->default(true);
            $table->boolean('show_on_page')->default(true);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
