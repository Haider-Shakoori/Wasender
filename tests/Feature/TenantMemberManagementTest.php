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

final class TenantMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->tenant = Tenant::factory()->for($this->owner, 'owner')->create();
        $roles = app(RolePermissionService::class)->initializeForTenant($this->tenant);
        TenantMembership::factory()->for($this->tenant)->for($this->owner)->for($roles->get('owner'))->active()->create();
        $this->owner->update(['last_active_tenant_id' => $this->tenant->id]);
        $this->actingAs($this->owner)->withSession(['active_tenant_id' => $this->tenant->id]);
    }

    public function test_role_change_suspend_reactivate_and_remove_are_audited(): void
    {
        $user = User::factory()->create(['last_active_tenant_id' => $this->tenant->id]);
        $viewer = $this->tenant->roles()->where('slug', 'viewer')->firstOrFail();
        $developer = $this->tenant->roles()->where('slug', 'developer')->firstOrFail();
        $membership = TenantMembership::factory()->for($this->tenant)->for($user)->for($viewer)->active()->create();

        $this->put(route('tenant.team.members.role.update', $membership->uuid), ['role_id' => $developer->id])->assertRedirect();
        $this->assertSame($developer->id, $membership->fresh()->role_id);
        $this->post(route('tenant.team.members.suspend', $membership->uuid))->assertRedirect();
        $this->assertSame(MembershipStatus::Suspended, $membership->fresh()->status);
        $this->assertNull($user->fresh()->last_active_tenant_id);
        $this->post(route('tenant.team.members.reactivate', $membership->uuid))->assertRedirect();
        $this->assertSame(MembershipStatus::Active, $membership->fresh()->status);
        $this->delete(route('tenant.team.members.destroy', $membership->uuid))->assertRedirect();
        $this->assertSame(MembershipStatus::Removed, $membership->fresh()->status);
        foreach (['tenant.member_role_changed', 'tenant.member_suspended', 'tenant.member_reactivated', 'tenant.member_removed'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['tenant_id' => $this->tenant->id, 'action' => $action]);
        }
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_owner_and_self_actions_are_blocked(): void
    {
        $ownerMembership = TenantMembership::where('tenant_id', $this->tenant->id)->where('user_id', $this->owner->id)->sole();
        $this->post(route('tenant.team.members.suspend', $ownerMembership->uuid))->assertForbidden();
        $this->delete(route('tenant.team.members.destroy', $ownerMembership->uuid))->assertForbidden();

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $adminRole = $this->tenant->roles()->where('slug', 'administrator')->firstOrFail();
        $membership = TenantMembership::factory()->for($this->tenant)->for($admin)->for($adminRole)->active()->create();
        $this->actingAs($admin)->withSession(['active_tenant_id' => $this->tenant->id]);
        $this->post(route('tenant.team.members.suspend', $membership->uuid))->assertForbidden();
        $this->delete(route('tenant.team.members.destroy', $membership->uuid))->assertForbidden();
    }

    public function test_cross_tenant_membership_and_role_are_not_mutable(): void
    {
        $foreignOwner = User::factory()->create();
        $foreign = Tenant::factory()->for($foreignOwner, 'owner')->create();
        $foreignRoles = app(RolePermissionService::class)->initializeForTenant($foreign);
        $foreignMember = TenantMembership::factory()->for($foreign)->for(User::factory())->for($foreignRoles->get('viewer'))->active()->create();
        $localMember = TenantMembership::factory()->for($this->tenant)->for(User::factory())->for($this->tenant->roles()->where('slug', 'viewer')->first())->active()->create();
        $this->post(route('tenant.team.members.suspend', $foreignMember->uuid))->assertNotFound();
        $this->put(route('tenant.team.members.role.update', $localMember->uuid), ['role_id' => $foreignRoles->get('viewer')->id])
            ->assertSessionHasErrors('role_id');
    }

    public function test_last_effective_non_owner_administrator_is_protected(): void
    {
        $adminRole = $this->tenant->roles()->where('slug', 'administrator')->firstOrFail();
        $viewer = $this->tenant->roles()->where('slug', 'viewer')->firstOrFail();
        $first = TenantMembership::factory()->for($this->tenant)->for(User::factory())->for($adminRole)->active()->create();
        $this->post(route('tenant.team.members.suspend', $first->uuid))->assertSessionHasErrors('member');
        $this->put(route('tenant.team.members.role.update', $first->uuid), ['role_id' => $viewer->id])->assertSessionHasErrors('member');
        $second = TenantMembership::factory()->for($this->tenant)->for(User::factory())->for($adminRole)->active()->create();
        $this->post(route('tenant.team.members.suspend', $first->uuid))->assertRedirect();
        $this->assertSame(MembershipStatus::Suspended, $first->fresh()->status);
        $this->assertSame(MembershipStatus::Active, $second->fresh()->status);
    }

    public function test_team_and_invitation_pages_are_tenant_scoped_and_use_public_uuids(): void
    {
        $local = TenantMembership::factory()->for($this->tenant)->for(User::factory(['name' => 'Local Person']))->for($this->tenant->roles()->where('slug', 'viewer')->first())->active()->create();
        $foreignOwner = User::factory()->create();
        $foreign = Tenant::factory()->for($foreignOwner, 'owner')->create();
        $foreignRoles = app(RolePermissionService::class)->initializeForTenant($foreign);
        TenantMembership::factory()->for($foreign)->for(User::factory(['name' => 'Foreign Person']))->for($foreignRoles->get('viewer'))->active()->create();
        $this->get(route('tenant.team.index'))->assertOk()->assertSee('Local Person')->assertDontSee('Foreign Person')
            ->assertSee($local->uuid)->assertDontSee('/members/'.$local->id.'/');
    }
}
