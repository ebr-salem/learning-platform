<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every WhatsApp send attempt this platform makes, successful or not.
     */
    public function up(): void
    {
        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->id();

            // auto: triggered by the app (attendance), manual: ad-hoc from the
            // dashboard, test: the settings page connectivity check.
            $table->string('source')->default('auto');

            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name')->nullable();

            // The number as stored on the record, and the chat id WAHA was
            // actually called with - kept apart so a normalisation bug is visible.
            $table->string('phone')->nullable();
            $table->string('chat_id');

            $table->string('session')->nullable();
            $table->text('message');
            $table->string('status');

            $table->string('message_id')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('error')->nullable();

            // Whether the SMSMisr fallback rescued this send after WhatsApp failed.
            $table->boolean('sms_fallback_sent')->default(false);

            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_logs');
    }
};
