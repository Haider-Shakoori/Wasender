<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\TenantContext;
use App\Enums\MembershipStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\RolePermissionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class TenantDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_redirects_to_login_and_unverified_user_to_notice(): void
    {
        $this->get(route('tenant.dashboard'))->assertRedirect(route('login'));
        [$user] = $this->member(false);
        $this->actingAs($user)->get(route('tenant.dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_verified_member_sees_current_tenant_role_and_real_member_count(): void
    {
        [$user, $tenant] = $this->member();
        $foreign = Tenant::factory()->create(['name' => 'Hidden Company']);

        $this->actingAs($user)->get(route('tenant.dashboard'))
            ->assertOk()
            ->assertSee($tenant->name)
            ->assertSee('Viewer')
            ->assertSee('Team members')
            ->assertDontSee($foreign->name);
        $this->assertFalse(app(TenantContext::class)->check(), 'Request-scoped context should be cleared after response.');
        $this->assertFalse(Schema::hasTable('messages'));
    }

    public function test_no_membership_and_suspended_workspace_have_safe_access_pages(): void
    {
        $none = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($none)->get(route('tenant.dashboard'))->assertRedirect(route('tenant.no-workspace'));

        [$user, $tenant] = $this->member();
        $tenant->update(['status' => 'suspended', 'is_active' => false]);
        $this->actingAs($user)->get(route('tenant.dashboard'))->assertRedirect(route('tenant.access.restricted'));
    }

    /** @return array{User,Tenant} */
    private function member(bool $verified = true): array
    {
        $user = User::factory()->create(['email_verified_at' => $verified ? now() : null]);
        $tenant = Tenant::factory()->for($user, 'owner')->create();
        $role = app(RolePermissionService::class)->initializeForTenant($tenant)->get('viewer');
        TenantMembership::create([
            'tenant_id' => $tenant->id, 'user_id' => $user->id, 'role_id' => $role->id,
            'status' => MembershipStatus::Active, 'joined_at' => now(),
        ]);
        $user->update(['last_active_tenant_id' => $tenant->id]);

        return [$user, $tenant];
    }
}
