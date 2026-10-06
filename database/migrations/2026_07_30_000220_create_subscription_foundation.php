<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('description', 1000)->nullable();
            $t->string('status', 20)->index();
            $t->boolean('is_public')->default(false);
            $t->boolean('is_featured')->default(false);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->string('billing_interval', 20);
            $t->unsignedBigInteger('price_amount')->nullable();
            $t->char('price_currency', 3)->nullable();
            $t->unsignedSmallInteger('trial_days')->default(0);
            $t->unsignedSmallInteger('grace_days')->default(0);
            $t->boolean('is_system')->default(false);
            $t->timestamps();
            $t->index(['status', 'is_public', 'sort_order'], 'sub_plans_public_idx');
        });
        Schema::create('subscription_plan_features', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $t->string('feature_key', 100);
            $t->string('value_type', 20);
            $t->boolean('boolean_value')->nullable();
            $t->unsignedBigInteger('integer_value')->nullable();
            $t->string('string_value', 500)->nullable();
            $t->boolean('is_unlimited')->default(false);
            $t->timestamps();
            $t->unique(['plan_id', 'feature_key'], 'sub_plan_feature_uq');
        });
        Schema::create('tenant_subscriptions', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $t->string('status', 20);
            $t->string('source', 20);
            $t->boolean('is_current')->default(true);
            $t->timestamp('starts_at');
            $t->timestamp('trial_ends_at')->nullable();
            $t->timestamp('current_period_starts_at')->nullable();
            $t->timestamp('current_period_ends_at')->nullable();
            $t->timestamp('grace_ends_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('provider', 50)->nullable();
            $t->string('provider_subscription_id', 191)->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'is_current'], 'tenant_sub_current_idx');
            $t->index(['status', 'trial_ends_at'], 'tenant_sub_trial_idx');
            $t->index(['status', 'grace_ends_at'], 'tenant_sub_grace_idx');
        });
        Schema::create('subscription_status_history', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('subscription_id')->constrained('tenant_subscriptions')->cascadeOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->string('reason', 500)->nullable();
            $t->string('actor_type', 20);
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->timestamp('effective_at');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['subscription_id', 'effective_at'], 'sub_history_time_idx');
        });
        Schema::create('subscription_usage_snapshots', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $t->string('metric_key', 100);
            $t->unsignedBigInteger('value');
            $t->timestamp('period_starts_at');
            $t->timestamp('captured_at');
            $t->timestamps();
            $t->unique(['tenant_id', 'metric_key', 'period_starts_at'], 'sub_usage_window_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_usage_snapshots');
        Schema::dropIfExists('subscription_status_history');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('subscription_plan_features');
        Schema::dropIfExists('subscription_plans');
    }
};
