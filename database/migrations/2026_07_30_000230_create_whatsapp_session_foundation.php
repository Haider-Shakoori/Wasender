<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('storage_key', 64)->unique();
            $table->string('status', 24);
            $table->string('phone_number', 32)->nullable();
            $table->string('display_name', 160)->nullable();
            $table->string('platform', 40)->nullable();
            $table->string('wid', 100)->nullable();
            $table->timestamp('last_qr_generated_at')->nullable();
            $table->unsignedInteger('qr_generation_count')->default(0);
            $table->timestamp('authenticated_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->string('disconnect_reason', 500)->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->unsignedSmallInteger('reconnect_attempts')->default(0);
            $table->timestamp('last_reconnect_attempt_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'status'], 'wa_sessions_tenant_status_idx');
            $table->index(['status', 'last_seen_at'], 'wa_sessions_health_idx');
        });

        Schema::create('whatsapp_session_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_session_id')->constrained()->cascadeOnDelete();
            $table->string('event', 80);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->string('source', 20);
            $table->string('reason_code', 80)->nullable();
            $table->string('message', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'occurred_at'], 'wa_events_tenant_time_idx');
            $table->index(['whatsapp_session_id', 'occurred_at'], 'wa_events_session_time_idx');
        });

        Schema::create('whatsapp_callback_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('whatsapp_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 80);
            $table->timestamp('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_callback_events');
        Schema::dropIfExists('whatsapp_session_events');
        Schema::dropIfExists('whatsapp_sessions');
    }
};
