<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 32);
            $table->string('message_type', 16);
            $table->text('body')->nullable();
            $table->json('content_metadata')->nullable();
            $table->string('audience_type', 32);
            $table->json('audience_config')->nullable();
            $table->string('session_strategy', 24);
            $table->json('session_config')->nullable();
            $table->string('schedule_type', 16);
            $table->string('scheduled_at_local', 40)->nullable();
            $table->timestamp('scheduled_at_utc')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->json('send_window_config')->nullable();
            $table->json('execution_config')->nullable();
            $table->char('payload_hash', 64);
            $table->unsignedBigInteger('estimated_recipient_count')->nullable();
            foreach (['snapshot', 'eligible', 'excluded', 'queued', 'processing', 'sent', 'delivered', 'read', 'failed', 'cancelled', 'skipped'] as $counter) {
                $table->unsignedBigInteger($counter.'_recipient_count')->default(0);
            }
            $table->decimal('progress_percentage', 5, 2)->default(0);
            foreach (['created_by', 'updated_by', 'approved_by', 'launched_by', 'paused_by', 'cancelled_by', 'completed_by'] as $actor) {
                $table->foreignId($actor)->nullable()->constrained('users')->nullOnDelete();
            }
            foreach (['approved_at', 'prepared_at', 'launch_requested_at', 'started_at', 'paused_at', 'resumed_at', 'completed_at', 'cancelled_at', 'failed_at', 'archived_at', 'last_progress_at'] as $stamp) {
                $table->timestamp($stamp)->nullable();
            }
            $table->string('failure_code', 80)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'status'], 'wa_cmp_tenant_status_idx');
            $table->index(['tenant_id', 'created_at'], 'wa_cmp_tenant_time_idx');
            $table->index(['tenant_id', 'scheduled_at_utc'], 'wa_cmp_tenant_sched_idx');
            $table->index(['tenant_id', 'created_by'], 'wa_cmp_tenant_creator_idx');
            $table->index(['status', 'scheduled_at_utc'], 'wa_cmp_status_sched_idx');
        });

        Schema::create('whatsapp_campaign_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_campaign_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('disk', 32);
            $table->string('storage_key')->unique();
            $table->string('original_name');
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
        });

        Schema::create('whatsapp_campaign_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_session_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['whatsapp_campaign_id', 'whatsapp_session_id'], 'wa_cmp_session_uq');
        });

        Schema::create('whatsapp_campaign_audience_refs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('reference_type', 16);
            $table->uuid('reference_uuid');
            $table->timestamps();
            $table->unique(['whatsapp_campaign_id', 'reference_type', 'reference_uuid'], 'wa_cmp_aud_ref_uq');
            $table->index(['tenant_id', 'reference_type', 'reference_uuid'], 'wa_cmp_aud_tenant_idx');
        });

        Schema::create('whatsapp_campaign_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('event', 80);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->string('source', 20);
            $table->string('actor_type', 120)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->uuid('event_id')->nullable()->unique();
            $table->string('reason_code', 80)->nullable();
            $table->string('message', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['whatsapp_campaign_id', 'occurred_at'], 'wa_cmp_evt_time_idx');
            $table->index(['tenant_id', 'occurred_at'], 'wa_cmp_evt_tenant_idx');
        });

        Schema::create('whatsapp_campaign_idempotency', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('operation', 24);
            $table->string('idempotency_key', 80);
            $table->char('payload_hash', 64);
            $table->foreignId('whatsapp_campaign_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'operation', 'idempotency_key'], 'wa_cmp_idem_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaign_idempotency');
        Schema::dropIfExists('whatsapp_campaign_events');
        Schema::dropIfExists('whatsapp_campaign_audience_refs');
        Schema::dropIfExists('whatsapp_campaign_sessions');
        Schema::dropIfExists('whatsapp_campaign_attachments');
        Schema::dropIfExists('whatsapp_campaigns');
    }
};
