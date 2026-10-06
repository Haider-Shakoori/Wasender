<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('whatsapp_address', 40);
            $table->string('normalized_phone', 16);
            $table->string('status', 16)->default('open');
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->string('last_message_direction', 12)->nullable();
            $table->string('last_message_type', 16)->nullable();
            $table->string('last_message_preview', 255)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('first_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'whatsapp_session_id', 'whatsapp_address'], 'wa_conv_identity_uq');
            $table->index(['tenant_id', 'status'], 'wa_conv_tenant_status_idx');
            $table->index(['tenant_id', 'last_message_at'], 'wa_conv_tenant_activity_idx');
            $table->index(['tenant_id', 'unread_count'], 'wa_conv_tenant_unread_idx');
            $table->index(['contact_id', 'last_message_at'], 'wa_conv_contact_activity_idx');
        });

        Schema::create('whatsapp_inbox_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('outbound_message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
            $table->string('direction', 12);
            $table->string('message_type', 16);
            $table->string('status', 16);
            $table->string('whatsapp_message_id', 191)->nullable();
            $table->string('whatsapp_serialized_id', 512)->nullable();
            $table->string('reply_to_whatsapp_message_id', 191)->nullable();
            $table->foreignId('reply_to_message_id')->nullable()->constrained('whatsapp_inbox_messages')->nullOnDelete();
            $table->string('sender_address', 40);
            $table->string('recipient_address', 40);
            $table->text('body')->nullable();
            $table->text('caption')->nullable();
            $table->string('message_preview', 255);
            $table->string('media_status', 12)->default('none');
            $table->string('media_mime_type', 120)->nullable();
            $table->unsignedBigInteger('media_size_bytes')->nullable();
            $table->char('media_checksum_sha256', 64)->nullable();
            $table->string('media_retrieval_reference', 512)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['whatsapp_session_id', 'whatsapp_message_id'], 'wa_inbox_session_message_uq');
            $table->unique('outbound_message_id', 'wa_inbox_outbound_uq');
            $table->index(['whatsapp_conversation_id', 'occurred_at'], 'wa_inbox_conv_time_idx');
            $table->index(['tenant_id', 'direction', 'occurred_at'], 'wa_inbox_tenant_dir_time_idx');
        });

        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->foreign('last_message_id', 'wa_conv_last_message_fk')->references('id')->on('whatsapp_inbox_messages')->nullOnDelete();
        });

        Schema::table('whatsapp_callback_events', function (Blueprint $table): void {
            $table->char('payload_hash', 64)->nullable()->after('event');
            $table->json('result')->nullable()->after('payload_hash');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_callback_events', function (Blueprint $table): void {
            $table->dropColumn(['payload_hash', 'result']);
        });
        Schema::table('whatsapp_conversations', fn (Blueprint $table) => $table->dropForeign('wa_conv_last_message_fk'));
        Schema::dropIfExists('whatsapp_inbox_messages');
        Schema::dropIfExists('whatsapp_conversations');
    }
};
