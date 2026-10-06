<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\TenantSubscriptionStatus;
use App\Enums\UserStatus;
use App\Models\PlatformRole;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\PlatformRoleAssignmentService;
use App\Services\RolePermissionService;
use App\Services\SubscriptionBootstrapService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubscriptionInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_super_admin_can_browse_create_and_edit_plans(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create(['status' => UserStatus::Active]);
        app(PlatformRoleAssignmentService::class)->grant($admin, PlatformRole::where('slug', 'super-admin')->firstOrFail());
        $this->actingAs($admin)->get(route('platform.plans.index'))->assertOk()->assertSee('Subscription plans');
        $response = $this->post(route('platform.plans.store'), ['name' => 'Partner', 'slug' => 'partner', 'status' => 'active', 'billing_interval' => 'yearly', 'trial_days' => 0, 'grace_days' => 5, 'features' => ['dashboard.access'], 'limits' => ['team_members.max' => 25]]);
        $plan = SubscriptionPlan::where('slug', 'partner')->firstOrFail();
        $response->assertRedirect(route('platform.plans.show', $plan));
        $this->get(route('platform.plans.show', $plan))->assertOk()->assertSee('No payment provider connected');
    }

    public function test_unauthorized_platform_user_cannot_manage_plans(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $this->actingAs($user)->get(route('platform.plans.index'))->assertForbidden();
        $this->post(route('platform.plans.store'), [])->assertForbidden();
    }

    public function test_tenant_subscription_page_shows_real_plan_limits_and_no_checkout(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::factory()->create(['status' => UserStatus::Active]);
        $tenant = Tenant::factory()->for($owner, 'owner')->create();
        $role = app(RolePermissionService::class)->initializeForTenant($tenant)->get('owner');
        TenantMembership::factory()->for($tenant)->for($owner)->for($role)->create(['status' => MembershipStatus::Active, 'joined_at' => now()]);
        app(SubscriptionBootstrapService::class)->assignDefault($tenant);
        $this->actingAs($owner)->withSession(['active_tenant_id' => $tenant->id])->get(route('tenant.subscription.show'))->assertOk()->assertSee('Trial')->assertSee('Active team members')->assertSee('Online checkout is not available')->assertDontSee('Pay now');
    }

    public function test_restricted_subscription_still_reaches_overview(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::factory()->create(['status' => UserStatus::Active]);
        $tenant = Tenant::factory()->for($owner, 'owner')->create();
        $role = app(RolePermissionService::class)->initializeForTenant($tenant)->get('owner');
        TenantMembership::factory()->for($tenant)->for($owner)->for($role)->active()->create();
        $sub = app(SubscriptionBootstrapService::class)->assignDefault($tenant);
        $sub->update(['status' => TenantSubscriptionStatus::Expired]);
        $this->actingAs($owner)->withSession(['active_tenant_id' => $tenant->id])->get(route('tenant.subscription.show'))->assertOk()->assertSee('modules are restricted');
    }
}
