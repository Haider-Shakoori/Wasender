<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\RolePermissionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkspaceSelectionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_multiple_without_selection_redirects_to_filtered_selection_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $first = $this->membership($user, 'Alpha');
        $second = $this->membership($user, 'Beta');
        $hidden = $this->membership($user, 'Hidden', MembershipStatus::Suspended);

        $this->actingAs($user)->get(route('tenant.dashboard'))->assertRedirect(route('tenant.workspaces.index'));
        $this->get(route('tenant.workspaces.index'))->assertOk()
            ->assertSee('Alpha')->assertSee('Beta')->assertDontSee('Hidden')
            ->assertSee($first->uuid)->assertSee($second->uuid)
            ->assertDontSee('/app/workspaces/'.$first->id.'/switch');
        $this->assertNotNull($hidden);
    }

    public function test_exactly_one_fallback_is_automatic_and_invalid_preferences_are_cleared(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $foreign = Tenant::factory()->create();
        $valid = $this->membership($user, 'Only');
        $user->update(['last_active_tenant_id' => $foreign->id]);

        $this->actingAs($user)->withSession(['active_tenant_id' => 'invalid'])
            ->get(route('tenant.dashboard'))->assertOk()->assertSee('Only');
        $this->assertSame($valid->id, session('active_tenant_id'));
        $this->assertSame($valid->id, $user->fresh()->last_active_tenant_id);
    }

    public function test_removed_active_membership_recovers_to_selection_or_no_workspace(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $removed = $this->membership($user, 'Removed', MembershipStatus::Removed);
        $first = $this->membership($user, 'Alpha');
        $this->membership($user, 'Beta');
        $user->update(['last_active_tenant_id' => $removed->id]);

        $this->actingAs($user)->withSession(['active_tenant_id' => $removed->id])
            ->get(route('tenant.dashboard'))->assertRedirect(route('tenant.workspaces.index'));
        $this->assertFalse(session()->has('active_tenant_id'));
        $this->assertNull($user->fresh()->last_active_tenant_id);

        TenantMembership::where('user_id', $user->id)->update(['status' => MembershipStatus::Removed]);
        $this->get(route('tenant.dashboard'))->assertRedirect(route('tenant.no-workspace'));
        $this->assertNotNull($first);
    }

    public function test_selection_routes_require_authentication_and_verification(): void
    {
        $this->get(route('tenant.workspaces.index'))->assertRedirect(route('login'));
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get(route('tenant.workspaces.index'))->assertRedirect(route('verification.notice'));
    }

    private function membership(User $user, string $name, MembershipStatus $status = MembershipStatus::Active): Tenant
    {
        $tenant = Tenant::factory()->for($user, 'owner')->create(['name' => $name]);
        $role = app(RolePermissionService::class)->initializeForTenant($tenant)->get('viewer');
        TenantMembership::create([
            'tenant_id' => $tenant->id, 'user_id' => $user->id, 'role_id' => $role->id,
            'status' => $status, 'joined_at' => $status === MembershipStatus::Active ? now() : null,
        ]);

        return $tenant;
    }
}
