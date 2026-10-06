<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_campaign_usage_reservations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('reserved');
            $table->unsignedBigInteger('reserved_units');
            $table->unsignedBigInteger('consumed_units')->default(0);
            $table->unsignedBigInteger('released_units')->default(0);
            $table->string('idempotency_key', 80);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key'], 'wa_usage_tenant_idem_uq');
            $table->index(['tenant_id', 'status'], 'wa_usage_tenant_status_idx');
        });
        Schema::create('whatsapp_campaign_executions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('preparation_id')->constrained('whatsapp_campaign_preparations')->restrictOnDelete();
            $table->foreignId('usage_reservation_id')->nullable()->constrained('whatsapp_campaign_usage_reservations')->nullOnDelete();
            $table->string('status', 24);
            $table->unsignedInteger('campaign_version');
            $table->char('campaign_payload_hash', 64);
            $table->char('execution_config_hash', 64);
            $table->string('idempotency_key', 80);
            $table->string('launch_type', 20);
            foreach (['scheduled_at', 'queued_at', 'started_at', 'pause_requested_at', 'paused_at', 'resume_requested_at', 'resumed_at', 'cancel_requested_at', 'cancelled_at', 'completed_at', 'failed_at', 'last_progress_at', 'last_heartbeat_at'] as $column) {
                $table->timestamp($column)->nullable();
            }
            foreach (['total', 'pending', 'queued', 'processing', 'sent', 'failed', 'skipped', 'cancelled', 'retry_scheduled', 'transport_pending'] as $counter) {
                $table->unsignedBigInteger($counter.'_recipients')->default(0);
            }
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->string('failure_code', 80)->nullable();
            $table->string('failure_message', 500)->nullable();
            foreach (['created_by', 'launched_by', 'paused_by', 'resumed_by', 'cancelled_by'] as $actor) {
                $table->foreignId($actor)->nullable()->constrained('users')->nullOnDelete();
            }
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['whatsapp_campaign_id', 'status'], 'wa_exec_campaign_status_idx');
            $table->index(['tenant_id', 'status'], 'wa_exec_tenant_status_idx');
            $table->index(['tenant_id', 'created_at'], 'wa_exec_tenant_time_idx');
            $table->index(['status', 'last_heartbeat_at'], 'wa_exec_heartbeat_idx');
            $table->index(['whatsapp_campaign_id', 'created_at'], 'wa_exec_campaign_time_idx');
            $table->unique(['tenant_id', 'idempotency_key'], 'wa_exec_tenant_idem_uq');
            $table->index(['preparation_id', 'status'], 'wa_exec_prep_status_idx');
        });
        Schema::create('whatsapp_campaign_recipient_executions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_campaign_id');
            $table->foreign('whatsapp_campaign_id', 'wa_rexec_campaign_fk')->references('id')->on('whatsapp_campaigns')->cascadeOnDelete();
            $table->foreignId('campaign_execution_id');
            $table->foreign('campaign_execution_id', 'wa_rexec_execution_fk')->references('id')->on('whatsapp_campaign_executions')->cascadeOnDelete();
            $table->foreignId('campaign_recipient_id');
            $table->foreign('campaign_recipient_id', 'wa_rexec_recipient_fk')->references('id')->on('whatsapp_campaign_recipients')->restrictOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('whatsapp_sessions')->nullOnDelete();
            $table->string('status', 24);
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('max_attempts');
            foreach (['next_attempt_at', 'claimed_at', 'processing_started_at', 'last_attempt_at', 'sent_at', 'failed_at', 'skipped_at', 'cancelled_at'] as $column) {
                $table->timestamp($column)->nullable();
            }
            $table->string('failure_code', 80)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->string('transport_reference')->nullable();
            $table->string('idempotency_key', 120);
            $table->smallInteger('priority')->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['campaign_execution_id', 'campaign_recipient_id'], 'wa_rexec_execution_recipient_uq');
            $table->unique(['campaign_execution_id', 'idempotency_key'], 'wa_rexec_execution_idem_uq');
            $table->index(['campaign_execution_id', 'status', 'next_attempt_at'], 'wa_rexec_claim_idx');
            $table->index(['tenant_id', 'status'], 'wa_rexec_tenant_status_idx');
            $table->index(['session_id', 'status'], 'wa_rexec_session_status_idx');
            $table->index(['status', 'claimed_at'], 'wa_rexec_stale_claim_idx');
        });
        Schema::create('whatsapp_campaign_dispatch_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_campaign_id');
            $table->foreign('whatsapp_campaign_id', 'wa_attempt_campaign_fk')->references('id')->on('whatsapp_campaigns')->cascadeOnDelete();
            $table->foreignId('campaign_execution_id');
            $table->foreign('campaign_execution_id', 'wa_attempt_execution_fk')->references('id')->on('whatsapp_campaign_executions')->cascadeOnDelete();
            $table->foreignId('campaign_recipient_id');
            $table->foreign('campaign_recipient_id', 'wa_attempt_recipient_fk')->references('id')->on('whatsapp_campaign_recipients')->restrictOnDelete();
            $table->foreignId('recipient_execution_id');
            $table->foreign('recipient_execution_id', 'wa_attempt_rexec_fk')->references('id')->on('whatsapp_campaign_recipient_executions')->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('whatsapp_sessions')->nullOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 24);
            $table->string('idempotency_key', 160)->unique();
            $table->char('transport_request_hash', 64);
            $table->string('transport_reference')->nullable();
            foreach (['queued_at', 'started_at', 'transport_requested_at', 'transport_accepted_at', 'succeeded_at', 'failed_at', 'next_retry_at'] as $column) {
                $table->timestamp($column)->nullable();
            }
            $table->string('failure_class', 20)->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['recipient_execution_id', 'attempt_number'], 'wa_attempt_recipient_number_uq');
            $table->index(['campaign_execution_id', 'status'], 'wa_attempt_execution_status_idx');
            $table->index(['session_id', 'status'], 'wa_attempt_session_status_idx');
            $table->index(['status', 'next_retry_at'], 'wa_attempt_retry_idx');
        });
        Schema::table('whatsapp_campaigns', function (Blueprint $table): void {
            $table->foreignId('active_execution_id')->nullable()->after('active_preparation_id')->constrained('whatsapp_campaign_executions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', fn (Blueprint $table) => $table->dropConstrainedForeignId('active_execution_id'));
        Schema::dropIfExists('whatsapp_campaign_dispatch_attempts');
        Schema::dropIfExists('whatsapp_campaign_recipient_executions');
        Schema::dropIfExists('whatsapp_campaign_executions');
        Schema::dropIfExists('whatsapp_campaign_usage_reservations');
    }
};
