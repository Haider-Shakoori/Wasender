<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_campaign_dispatch_attempts', function (Blueprint $table): void {
            $table->string('whatsapp_message_id', 512)->nullable()->after('transport_reference');
            $table->timestamp('unknown_since')->nullable()->after('failed_at');
            $table->timestamp('last_reconciled_at')->nullable()->after('unknown_since');
            $table->index(['status', 'unknown_since'], 'wa_attempt_unknown_idx');
            $table->index(['session_id', 'whatsapp_message_id'], 'wa_attempt_message_idx');
        });
        Schema::table('whatsapp_campaign_recipient_executions', function (Blueprint $table): void {
            $table->string('whatsapp_message_id', 512)->nullable()->after('transport_reference');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('read_at')->nullable()->after('delivered_at');
            $table->timestamp('usage_consumed_at')->nullable()->after('read_at');
            $table->timestamp('last_connector_event_at')->nullable()->after('usage_consumed_at');
        });
        Schema::table('whatsapp_campaign_executions', function (Blueprint $table): void {
            $table->unsignedBigInteger('delivered_recipients')->default(0)->after('sent_recipients');
            $table->unsignedBigInteger('read_recipients')->default(0)->after('delivered_recipients');
            $table->unsignedBigInteger('unknown_recipients')->default(0)->after('transport_pending_recipients');
            $table->timestamp('last_connector_event_at')->nullable()->after('last_heartbeat_at');
        });
        Schema::create('whatsapp_campaign_connector_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('event_id', 80)->unique();
            $table->string('source', 32)->default('whatsapp-node');
            $table->string('event_type', 80);
            $table->char('payload_hash', 64);
            $table->string('status', 24)->default('received');
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dispatch_attempt_id')->nullable()->constrained('whatsapp_campaign_dispatch_attempts')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at'], 'wa_connector_event_status_idx');
            $table->index(['tenant_id', 'created_at'], 'wa_connector_event_tenant_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaign_connector_events');
        Schema::table('whatsapp_campaign_executions', function (Blueprint $table): void {
            $table->dropColumn(['delivered_recipients', 'read_recipients', 'unknown_recipients', 'last_connector_event_at']);
        });
        Schema::table('whatsapp_campaign_recipient_executions', function (Blueprint $table): void {
            $table->dropColumn(['whatsapp_message_id', 'delivered_at', 'read_at', 'usage_consumed_at', 'last_connector_event_at']);
        });
        Schema::table('whatsapp_campaign_dispatch_attempts', function (Blueprint $table): void {
            $table->dropIndex('wa_attempt_unknown_idx');
            $table->dropIndex('wa_attempt_message_idx');
            $table->dropColumn(['whatsapp_message_id', 'unknown_since', 'last_reconciled_at']);
        });
    }
};
