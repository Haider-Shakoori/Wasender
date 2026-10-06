<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('name', 120);
            $table->string('status', 20)->default('active');
            $table->boolean('is_enabled')->default(true);
            $table->json('configuration')->nullable();
            $table->text('credentials_encrypted')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->string('last_failure_code', 80)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'provider', 'status'], 'integration_tenant_status_idx');
        });
        Schema::create('integration_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->string('external_event_id', 191)->nullable();
            $table->string('status', 20)->default('received');
            $table->char('payload_hash', 64);
            $table->uuid('related_message_uuid')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['integration_id', 'event_type', 'external_event_id'], 'integration_event_idempotency_uq');
            $table->index(['tenant_id', 'created_at'], 'integration_event_tenant_idx');
        });
        Schema::create('integration_webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->string('event_id', 191);
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->char('payload_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['integration_id', 'event_type', 'event_id'], 'integration_delivery_event_uq');
            $table->index(['tenant_id', 'created_at'], 'integration_delivery_tenant_idx');
        });
        $now = now();
        foreach (array_filter(config('roles.permissions'), fn (string $slug) => str_starts_with($slug, 'integrations.')) as $slug) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], ['name' => str($slug)->replace(['.', '_'], ' ')->title(), 'description' => 'Use the '.str($slug)->replace(['.', '_'], ' ')->lower().' capability.', 'created_at' => $now, 'updated_at' => $now]);
        }
        $permissionIds = DB::table('permissions')->where('slug', 'like', 'integrations.%')->pluck('id');
        foreach (DB::table('roles')->whereIn('slug', ['owner', 'administrator'])->pluck('id') as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId], ['created_at' => $now, 'updated_at' => $now]);
            }
        }
        DB::table('platform_permissions')->updateOrInsert(['slug' => 'platform.integrations.view'], ['name' => 'View integrations', 'created_at' => $now, 'updated_at' => $now]);
        $pid = DB::table('platform_permissions')->where('slug', 'platform.integrations.view')->value('id');
        foreach (DB::table('platform_roles')->where('slug', 'super-admin')->pluck('id') as $roleId) {
            DB::table('platform_permission_role')->updateOrInsert(['platform_role_id' => $roleId, 'platform_permission_id' => $pid]);
        }
        foreach (DB::table('subscription_plans')->whereIn('slug', ['growth', 'business'])->pluck('id') as $planId) {
            DB::table('subscription_plan_features')->updateOrInsert(['plan_id' => $planId, 'feature_key' => 'integrations.access'], ['value_type' => 'boolean', 'boolean_value' => true, 'integer_value' => null, 'string_value' => null, 'is_unlimited' => false, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_webhook_deliveries');
        Schema::dropIfExists('integration_events');
        Schema::dropIfExists('integrations');
    }
};
