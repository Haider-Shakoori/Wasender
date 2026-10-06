<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('recipient', 40);
            $table->string('recipient_normalized', 15);
            $table->string('recipient_display', 80)->nullable();
            $table->string('message_type', 16);
            $table->text('body')->nullable();
            $table->string('status', 20);
            $table->string('idempotency_key', 80)->nullable();
            $table->char('payload_hash', 64);
            $table->uuid('connector_request_id')->nullable()->unique();
            $table->string('whatsapp_message_id', 191)->nullable()->index('wa_msg_external_idx');
            $table->string('client_reference', 100)->nullable();
            foreach (['queued_at', 'processing_at', 'sending_at', 'sent_at', 'delivered_at', 'read_at', 'failed_at', 'cancelled_at', 'expired_at', 'last_attempt_at', 'next_retry_at', 'expires_at'] as $column) {
                $table->timestamp($column)->nullable();
            }
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(3);
            $table->string('failure_code', 80)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->boolean('failure_retryable')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'created_at'], 'wa_msg_tenant_time_idx');
            $table->index(['tenant_id', 'status'], 'wa_msg_tenant_status_idx');
            $table->index(['tenant_id', 'recipient_normalized'], 'wa_msg_tenant_recipient_idx');
            $table->index(['whatsapp_session_id', 'status'], 'wa_msg_session_status_idx');
            $table->index(['status', 'next_retry_at'], 'wa_msg_retry_idx');
            $table->unique(['tenant_id', 'idempotency_key'], 'wa_msg_tenant_idem_uq');
        });
        Schema::create('whatsapp_message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_message_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('disk', 32);
            $table->string('storage_key', 255)->unique();
            $table->string('original_name', 255);
            $table->string('safe_name', 160);
            $table->string('mime_type', 120);
            $table->string('extension', 12);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->string('media_category', 16);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'created_at'], 'wa_att_tenant_time_idx');
        });
        Schema::create('whatsapp_message_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_message_id')->constrained()->cascadeOnDelete();
            $table->string('event', 80);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->string('source', 20);
            $table->uuid('event_uuid')->nullable()->unique();
            $table->string('reason_code', 80)->nullable();
            $table->string('message', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['whatsapp_message_id', 'occurred_at'], 'wa_evt_message_time_idx');
            $table->index(['tenant_id', 'occurred_at'], 'wa_evt_tenant_time_idx');
        });
        Schema::create('whatsapp_message_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_message_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->uuid('connector_request_id')->unique();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 20);
            $table->string('failure_code', 80)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->boolean('retryable')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['whatsapp_message_id', 'attempt_number'], 'wa_attempt_message_number_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_attempts');
        Schema::dropIfExists('whatsapp_message_events');
        Schema::dropIfExists('whatsapp_message_attachments');
        Schema::dropIfExists('whatsapp_messages');
    }
};
