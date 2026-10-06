<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_campaign_preparations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->unsignedInteger('campaign_version');
            $table->char('campaign_payload_hash', 64);
            $table->string('audience_type', 32);
            $table->char('audience_definition_hash', 64);
            $table->string('idempotency_key', 80);
            $table->unsignedBigInteger('total_candidates')->default(0);
            $table->unsignedBigInteger('processed_candidates')->default(0);
            $table->unsignedBigInteger('eligible_count')->default(0);
            $table->unsignedBigInteger('excluded_count')->default(0);
            $table->unsignedBigInteger('duplicate_count')->default(0);
            $table->unsignedBigInteger('invalid_count')->default(0);
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->json('cursor_state')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['whatsapp_campaign_id', 'status'], 'wa_prep_campaign_status_idx');
            $table->index(['tenant_id', 'created_at'], 'wa_prep_tenant_time_idx');
            $table->index(['tenant_id', 'status'], 'wa_prep_tenant_status_idx');
            $table->index(['whatsapp_campaign_id', 'created_at'], 'wa_prep_campaign_time_idx');
            $table->index(['status', 'updated_at'], 'wa_prep_stuck_idx');
            $table->unique(['tenant_id', 'idempotency_key'], 'wa_prep_tenant_idem_uq');
        });

        Schema::create('whatsapp_campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('preparation_id')->constrained('whatsapp_campaign_preparations')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('contact_uuid');
            $table->string('phone_normalized', 20);
            $table->string('whatsapp_address', 80)->nullable();
            $table->string('display_name', 180)->nullable();
            $table->string('preferred_language', 16)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('consent_status', 20);
            $table->string('recipient_status', 20)->default('prepared');
            $table->string('source_type', 32);
            $table->uuid('source_reference')->nullable();
            $table->char('deduplication_key', 64);
            $table->json('snapshot_data')->nullable();
            $table->unsignedInteger('campaign_version');
            $table->char('campaign_payload_hash', 64);
            $table->timestamp('prepared_at');
            $table->timestamps();
            $table->unique(['whatsapp_campaign_id', 'deduplication_key'], 'wa_recipient_campaign_dedupe_uq');
            $table->index(['whatsapp_campaign_id', 'contact_id'], 'wa_recipient_campaign_contact_idx');
            $table->index(['preparation_id', 'created_at'], 'wa_recipient_prep_time_idx');
            $table->index(['tenant_id', 'whatsapp_campaign_id'], 'wa_recipient_tenant_campaign_idx');
            $table->index(['whatsapp_campaign_id', 'campaign_version'], 'wa_recipient_campaign_ver_idx');
        });

        Schema::create('whatsapp_campaign_exclusions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('preparation_id')->constrained('whatsapp_campaign_preparations')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('contact_uuid')->nullable();
            $table->char('deduplication_key', 64)->nullable();
            $table->string('reason_code', 40);
            $table->json('reason_codes')->nullable();
            $table->string('source_type', 32);
            $table->uuid('source_reference')->nullable();
            $table->string('safe_summary', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['whatsapp_campaign_id', 'reason_code'], 'wa_excl_campaign_reason_idx');
            $table->index(['preparation_id', 'reason_code'], 'wa_excl_prep_reason_idx');
            $table->index(['tenant_id', 'whatsapp_campaign_id'], 'wa_excl_tenant_campaign_idx');
            $table->index('contact_id', 'wa_excl_contact_idx');
        });

        Schema::table('whatsapp_campaigns', function (Blueprint $table): void {
            $table->foreignId('active_preparation_id')->nullable()->after('payload_hash')->constrained('whatsapp_campaign_preparations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('active_preparation_id');
        });
        Schema::dropIfExists('whatsapp_campaign_exclusions');
        Schema::dropIfExists('whatsapp_campaign_recipients');
        Schema::dropIfExists('whatsapp_campaign_preparations');
    }
};
