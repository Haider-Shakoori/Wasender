<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_message_template_usages', fn (Blueprint $table) => $table->index(['tenant_id', 'created_at'], 'wa_tpl_usage_tenant_time_idx'));
        Schema::table('subscription_usage_snapshots', fn (Blueprint $table) => $table->index(['tenant_id', 'captured_at'], 'sub_usage_tenant_capture_idx'));
        Schema::table('whatsapp_conversation_notes', fn (Blueprint $table) => $table->index(['tenant_id', 'created_at'], 'wa_note_tenant_time_idx'));
    }

    public function down(): void
    {
        Schema::table('whatsapp_message_template_usages', fn (Blueprint $table) => $table->dropIndex('wa_tpl_usage_tenant_time_idx'));
        Schema::table('subscription_usage_snapshots', fn (Blueprint $table) => $table->dropIndex('sub_usage_tenant_capture_idx'));
        Schema::table('whatsapp_conversation_notes', fn (Blueprint $table) => $table->dropIndex('wa_note_tenant_time_idx'));
    }
};
