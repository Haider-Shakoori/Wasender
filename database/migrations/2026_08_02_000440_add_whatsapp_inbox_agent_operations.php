<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->foreignId('assigned_user_id')->nullable()->after('contact_id')->constrained('users')->nullOnDelete();
            $table->string('priority', 12)->default('normal')->after('status');
            $table->timestamp('last_read_at')->nullable();
            $table->foreignId('last_read_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_agent_activity_at')->nullable();
            $table->index(['tenant_id', 'assigned_user_id', 'status'], 'wa_conv_assignee_status_idx');
            $table->index(['tenant_id', 'priority', 'status'], 'wa_conv_priority_status_idx');
        });

        Schema::create('whatsapp_conversation_notes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['whatsapp_conversation_id', 'created_at'], 'wa_note_conv_time_idx');
        });
        Schema::create('whatsapp_conversation_note_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('note_id')->constrained('whatsapp_conversation_notes')->cascadeOnDelete();
            $table->foreignId('mentioned_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['note_id', 'mentioned_user_id'], 'wa_note_mention_uq');
        });
        Schema::create('whatsapp_saved_replies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('shortcut', 50)->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'shortcut'], 'wa_reply_tenant_shortcut_uq');
            $table->index(['tenant_id', 'is_active'], 'wa_reply_tenant_active_idx');
        });
        Schema::create('whatsapp_conversation_labels', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100);
            $table->string('color', 24)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'slug'], 'wa_label_tenant_slug_uq');
        });
        Schema::create('whatsapp_conversation_label_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_conversation_label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['whatsapp_conversation_id', 'whatsapp_conversation_label_id'], 'wa_conv_label_uq');
        });
        Schema::create('whatsapp_conversation_activities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('activity_type', 40);
            $table->uuid('subject_uuid')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['whatsapp_conversation_id', 'occurred_at'], 'wa_activity_conv_time_idx');
            $table->index(['tenant_id', 'activity_type', 'occurred_at'], 'wa_activity_tenant_type_idx');
        });
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['whatsapp_conversation_activities', 'whatsapp_conversation_label_assignments', 'whatsapp_conversation_labels', 'whatsapp_saved_replies', 'whatsapp_conversation_note_mentions', 'whatsapp_conversation_notes'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->dropForeign(['assigned_user_id']);
            $table->dropForeign(['last_read_by']);
            $table->dropForeign(['closed_by']);
            $table->dropForeign(['archived_by']);
            $table->dropIndex('wa_conv_assignee_status_idx');
            $table->dropIndex('wa_conv_priority_status_idx');
            $table->dropColumn(['assigned_user_id', 'priority', 'last_read_at', 'last_read_by', 'closed_by', 'archived_by', 'last_agent_activity_at']);
        });
    }
};
