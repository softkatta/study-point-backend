<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('to_phone', 20);
            $table->string('message_type', 20)->default('text');
            $table->text('body')->nullable();
            $table->json('template_params')->nullable();
            $table->string('document_filename')->nullable();
            $table->string('document_path')->nullable();
            $table->string('provider', 30)->nullable();
            $table->string('external_id')->nullable()->index();
            $table->string('status', 20)->default('queued');
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['related_type', 'related_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
