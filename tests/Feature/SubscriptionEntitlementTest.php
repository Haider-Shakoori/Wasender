<?php

namespace Tests\Feature;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Enums\TenantSubscriptionStatus;
use App\Exceptions\SubscriptionTransitionException;
use App\Exceptions\TenantFeatureUnavailableException;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\RolePermissionService;
use App\Services\SubscriptionBootstrapService;
use App\Services\SubscriptionLifecycleService;
use App\Services\SubscriptionUsageRegistry;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubscriptionEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, SystemRoleSeeder::class, SubscriptionPlanSeeder::class]);
        $owner = User::factory()->create();
        $this->tenant = Tenant::factory()->for($owner, 'owner')->create();
        $roles = app(RolePermissionService::class)->initializeForTenant($this->tenant);
        TenantMembership::factory()->for($this->tenant)->for($owner)->for($roles->get('owner'))->active()->create();
        app(TenantContext::class)->set($this->tenant);
        $this->plan = SubscriptionPlan::where('slug', 'trial')->firstOrFail();
    }

    public function test_plan_uuid_enums_typed_features_and_explicit_unlimited(): void
    {
        $this->assertNotEmpty($this->plan->uuid);
        $this->assertSame('active', $this->plan->status->value);
        $this->assertSame('monthly', $this->plan->billing_interval->value);
        $business = SubscriptionPlan::where('slug', 'business')->firstOrFail();
        $limit = $business->features()->where('feature_key', 'team_members.max')->firstOrFail();
        $this->assertTrue($limit->is_unlimited);
        $this->assertNull($limit->integer_value);
    }

    public function test_bootstrap_is_idempotent_and_trial_is_absolute(): void
    {
        $service = app(SubscriptionBootstrapService::class);
        $first = $service->assignDefault($this->tenant);
        $second = $service->assignDefault($this->tenant);
        $this->assertTrue($first->is($second));
        $this->assertSame(TenantSubscriptionStatus::Trialing, $first->status);
        $this->assertNotNull($first->trial_ends_at);
        $this->assertDatabaseCount('tenant_subscriptions', 1);
    }

    public function test_entitlements_features_limits_usage_and_remaining_are_tenant_scoped(): void
    {
        $sub = app(SubscriptionBootstrapService::class)->assignDefault($this->tenant);
        $entitlements = app(TenantEntitlements::class);
        $this->assertTrue($entitlements->hasFeature('team.manage'));
        $this->assertFalse($entitlements->hasFeature('webhooks.manage'));
        $this->assertSame(2, $entitlements->limit('team_members.max')->value);
        $this->assertSame(1, $entitlements->usage('team_members.max'));
        $this->assertSame(1, $entitlements->remaining('team_members.max'));
        $this->assertTrue($entitlements->canConsume('team_members.max', 1));
        $this->assertFalse($entitlements->canConsume('team_members.max', 2));
    }

    public function test_missing_features_and_capacity_fail_safely(): void
    {
        app(SubscriptionBootstrapService::class)->assignDefault($this->tenant);
        $entitlements = app(TenantEntitlements::class);
        $this->expectException(TenantFeatureUnavailableException::class);
        $entitlements->requireFeature('webhooks.manage');
    }

    public function test_valid_lifecycle_transition_is_historic_and_invalid_transition_fails(): void
    {
        $sub = app(SubscriptionBootstrapService::class)->assignDefault($this->tenant);
        $service = app(SubscriptionLifecycleService::class);
        $service->transition($sub, TenantSubscriptionStatus::Active, 'Trial approved');
        $this->assertDatabaseHas('subscription_status_history', ['subscription_id' => $sub->id, 'from_status' => 'trialing', 'to_status' => 'active']);
        $this->expectException(SubscriptionTransitionException::class);
        $service->transition($sub->refresh(), TenantSubscriptionStatus::Active, 'No-op');
    }

    public function test_backfill_dry_run_and_idempotent_assignment(): void
    {
        $this->artisan('subscriptions:backfill', ['--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('tenant_subscriptions', 0);
        $this->artisan('subscriptions:backfill')->assertSuccessful();
        $this->artisan('subscriptions:backfill')->assertSuccessful();
        $this->assertDatabaseCount('tenant_subscriptions', 1);
    }

    public function test_usage_snapshot_is_idempotent_per_day(): void
    {
        app(SubscriptionBootstrapService::class)->assignDefault($this->tenant);
        $this->artisan('subscriptions:snapshot-usage')->assertSuccessful();
        $this->artisan('subscriptions:snapshot-usage')->assertSuccessful();
        $this->assertDatabaseCount('subscription_usage_snapshots', count(app(SubscriptionUsageRegistry::class)->measurableKeys()));
    }
}
