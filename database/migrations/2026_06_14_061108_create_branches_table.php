<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('city');
            $table->string('manager_name')->nullable();
            $table->string('manager_phone')->nullable();
            $table->text('address')->nullable();
            $table->unsignedInteger('capacity')->default(100);
            $table->string('status')->default('active');
            $table->decimal('revenue', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
