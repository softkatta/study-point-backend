<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->string('admission_code', 20)->unique();
            $table->string('source', 20)->default('online');
            $table->string('status', 20)->default('pending');
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 20);
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('emergency_name')->nullable();
            $table->string('emergency_phone', 20)->nullable();
            $table->string('emergency_relation')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_name')->nullable();
            $table->date('start_date');
            $table->unsignedTinyInteger('duration_months')->default(1);
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('payment_mode')->nullable();
            $table->string('payment_status', 20)->default('pending');
            $table->string('transaction_id')->nullable();
            $table->date('payment_date')->nullable();
            $table->boolean('documents_uploaded')->default(false);
            $table->string('referral_source')->nullable();
            $table->text('notes')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('follow_up_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'source']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
