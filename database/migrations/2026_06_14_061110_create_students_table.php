<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_code', 20)->unique();
            $table->string('verify_token', 32)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 20);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('city')->nullable();
            $table->string('blood_group', 10)->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('plan_name')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('admission_id')->nullable()->constrained()->nullOnDelete();
            $table->date('valid_from')->nullable();
            $table->date('expiry')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'branch_id']);
            $table->index('expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
