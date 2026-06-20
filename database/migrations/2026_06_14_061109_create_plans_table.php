<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name');
            $table->string('category', 50);
            $table->unsignedSmallInteger('duration_days')->default(30);
            $table->unsignedTinyInteger('duration_months')->default(1);
            $table->decimal('price', 10, 2);
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->string('badge', 80)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->json('highlights')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
