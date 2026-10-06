<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_chatbots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_enabled')->default(false);
            $table->unsignedInteger('priority')->default(100);
            $table->string('fallback_behavior', 30)->default('none');
            $table->json('fallback_configuration')->nullable();
            $table->boolean('handoff_on_failure')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'is_enabled']);
        });
        Schema::create('whatsapp_chatbot_session_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chatbot_id');
            $table->foreignId('whatsapp_session_id');
            $table->timestamps();
            $table->foreign('chatbot_id', 'chatbot_assign_bot_fk')->references('id')->on('whatsapp_chatbots')->cascadeOnDelete();
            $table->foreign('whatsapp_session_id', 'chatbot_assign_session_fk')->references('id')->on('whatsapp_sessions')->cascadeOnDelete();
            $table->unique('whatsapp_session_id', 'chatbot_assign_session_uq');
            $table->unique(['chatbot_id', 'whatsapp_session_id'], 'chatbot_assign_pair_uq');
            $table->index(['tenant_id', 'chatbot_id'], 'chatbot_assign_tenant_idx');
        });
        Schema::create('whatsapp_chatbot_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chatbot_id')->constrained('whatsapp_chatbots')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_enabled')->default(true);
            $table->string('match_type', 20);
            $table->string('match_value', 500)->nullable();
            $table->json('condition_definition')->nullable();
            $table->string('action_type', 30);
            $table->json('action_configuration')->nullable();
            $table->boolean('stop_processing')->default(true);
            $table->timestamps();
            $table->index(['chatbot_id', 'is_enabled', 'priority']);
        });
        Schema::create('whatsapp_chatbot_conversation_states', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chatbot_id');
            $table->foreignId('whatsapp_conversation_id');
            $table->foreignId('contact_id')->nullable();
            $table->string('state', 20)->default('active');
            $table->boolean('handoff_active')->default(false);
            $table->foreignId('last_rule_id')->nullable();
            $table->foreignId('last_processed_message_id')->nullable();
            $table->timestamp('last_bot_reply_at')->nullable();
            $table->timestamp('cooldown_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreign('chatbot_id', 'chatbot_state_bot_fk')->references('id')->on('whatsapp_chatbots')->cascadeOnDelete();
            $table->foreign('whatsapp_conversation_id', 'chatbot_state_conversation_fk')->references('id')->on('whatsapp_conversations')->cascadeOnDelete();
            $table->foreign('contact_id', 'chatbot_state_contact_fk')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('last_rule_id', 'chatbot_state_rule_fk')->references('id')->on('whatsapp_chatbot_rules')->nullOnDelete();
            $table->foreign('last_processed_message_id', 'chatbot_state_message_fk')->references('id')->on('whatsapp_inbox_messages')->nullOnDelete();
            $table->unique(['chatbot_id', 'whatsapp_conversation_id'], 'chatbot_state_pair_uq');
            $table->index(['tenant_id', 'whatsapp_conversation_id'], 'chatbot_state_tenant_idx');
        });
        Schema::create('whatsapp_chatbot_rule_executions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chatbot_id');
            $table->foreignId('rule_id')->nullable();
            $table->foreignId('whatsapp_conversation_id');
            $table->foreignId('inbound_message_id');
            $table->foreignId('outbound_message_id')->nullable();
            $table->string('action_type', 30)->nullable();
            $table->string('status', 20);
            $table->string('failure_code', 80)->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();
            $table->foreign('chatbot_id', 'chatbot_exec_bot_fk')->references('id')->on('whatsapp_chatbots')->cascadeOnDelete();
            $table->foreign('rule_id', 'chatbot_exec_rule_fk')->references('id')->on('whatsapp_chatbot_rules')->nullOnDelete();
            $table->foreign('whatsapp_conversation_id', 'chatbot_exec_conversation_fk')->references('id')->on('whatsapp_conversations')->cascadeOnDelete();
            $table->foreign('inbound_message_id', 'chatbot_exec_inbound_fk')->references('id')->on('whatsapp_inbox_messages')->cascadeOnDelete();
            $table->foreign('outbound_message_id', 'chatbot_exec_outbound_fk')->references('id')->on('whatsapp_messages')->nullOnDelete();
            $table->unique(['chatbot_id', 'inbound_message_id'], 'chatbot_exec_message_uq');
            $table->index(['tenant_id', 'processed_at'], 'chatbot_exec_tenant_idx');
        });
        $now = now();
        foreach (array_filter(config('roles.permissions'), fn (string $slug) => str_starts_with($slug, 'chatbots.')) as $slug) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], ['name' => str($slug)->replace(['.', '_'], ' ')->title(), 'description' => 'Use the '.str($slug)->replace(['.', '_'], ' ')->lower().' capability.', 'created_at' => $now, 'updated_at' => $now]);
        }
        $permissionIds = DB::table('permissions')->where('slug', 'like', 'chatbots.%')->pluck('id');
        $roleIds = DB::table('roles')->whereIn('slug', ['owner', 'administrator'])->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId], ['created_at' => $now, 'updated_at' => $now]);
            }
        }
        DB::table('platform_permissions')->updateOrInsert(['slug' => 'platform.chatbots.view'], ['name' => 'View rule-based chatbots', 'created_at' => $now, 'updated_at' => $now]);
        foreach (DB::table('platform_roles')->where('slug', 'super-admin')->pluck('id') as $roleId) {
            $pid = DB::table('platform_permissions')->where('slug', 'platform.chatbots.view')->value('id');
            DB::table('platform_permission_role')->updateOrInsert(['platform_role_id' => $roleId, 'platform_permission_id' => $pid]);
        }
        $featureIds = DB::table('subscription_plans')->whereIn('slug', ['growth', 'business'])->pluck('id');
        foreach ($featureIds as $planId) {
            DB::table('subscription_plan_features')->updateOrInsert(['plan_id' => $planId, 'feature_key' => 'chatbots.access'], ['value_type' => 'boolean', 'boolean_value' => true, 'integer_value' => null, 'string_value' => null, 'is_unlimited' => false, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_chatbot_rule_executions');
        Schema::dropIfExists('whatsapp_chatbot_conversation_states');
        Schema::dropIfExists('whatsapp_chatbot_rules');
        Schema::dropIfExists('whatsapp_chatbot_session_assignments');
        Schema::dropIfExists('whatsapp_chatbots');
    }
};
