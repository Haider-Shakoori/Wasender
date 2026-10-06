<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\RolePermissionService;
use App\Services\TenantPermissionCache;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class TenantRoleCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->tenant = Tenant::factory()->for($this->user, 'owner')->create();
        $owner = app(RolePermissionService::class)->initializeForTenant($this->tenant)->get('owner');
        TenantMembership::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'role_id' => $owner->id,
            'status' => MembershipStatus::Active, 'joined_at' => now(),
        ]);
        $this->user->update(['last_active_tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user)->withSession(['active_tenant_id' => $this->tenant->id]);
    }

    public function test_custom_role_crud_uses_uuid_tenant_scope_and_audits(): void
    {
        $permissions = Permission::whereIn('slug', ['roles.view', 'team.view'])->pluck('id')->all();
        $this->post(route('tenant.roles.store'), [
            'name' => 'Operations Manager', 'description' => 'Operations access.',
            'permissions' => $permissions, 'tenant_id' => 999, 'is_system' => true,
        ])->assertSessionHasErrors(['tenant_id', 'is_system']);
        $this->assertDatabaseMissing('roles', ['name' => 'Operations Manager']);

        $this->post(route('tenant.roles.store'), [
            'name' => 'Operations Manager', 'description' => 'Operations access.', 'permissions' => $permissions,
        ])->assertRedirect();
        $role = Role::where('tenant_id', $this->tenant->id)->where('slug', 'operations-manager')->firstOrFail();
        $this->assertNotEmpty($role->uuid);
        $this->assertFalse($role->is_system);
        $this->assertCount(2, $role->permissions);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.created']);

        $cache = app(TenantPermissionCache::class);
        $cache->forRole($role);
        $cacheKey = $cache->key($role);
        $this->assertTrue(Cache::has($cacheKey));
        $this->put(route('tenant.roles.update', $role->uuid), [
            'name' => 'Operations Lead', 'description' => 'Updated.', 'permissions' => [Permission::where('slug', 'team.view')->value('id')],
        ])->assertRedirect(route('tenant.roles.show', $role->uuid));
        $this->assertSame('operations-manager', $role->fresh()->slug);
        $this->assertSame('Operations Lead', $role->fresh()->name);
        $this->assertFalse(Cache::has($cacheKey));
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.permissions_changed']);

        $this->delete(route('tenant.roles.destroy', $role->uuid))->assertRedirect(route('tenant.roles.index'));
        $this->assertFalse(Cache::has($cacheKey));
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.deleted']);
    }

    public function test_duplicate_names_get_stable_unique_slugs_and_unknown_permissions_are_rejected(): void
    {
        $permission = Permission::where('slug', 'team.view')->value('id');
        foreach (['operations-manager', 'operations-manager-2'] as $expected) {
            $this->post(route('tenant.roles.store'), [
                'name' => 'Operations Manager', 'permissions' => [$permission],
            ])->assertRedirect();
            $this->assertDatabaseHas('roles', ['tenant_id' => $this->tenant->id, 'slug' => $expected]);
        }
        $this->post(route('tenant.roles.store'), [
            'name' => 'Invalid', 'permissions' => [999999],
        ])->assertSessionHasErrors('permissions.0');
    }

    public function test_owner_and_system_roles_are_protected_and_used_custom_role_is_blocked(): void
    {
        $owner = $this->tenant->roles()->where('slug', 'owner')->firstOrFail();
        $viewer = $this->tenant->roles()->where('slug', 'viewer')->firstOrFail();
        $this->get(route('tenant.roles.edit', $owner->uuid))->assertForbidden();
        $this->delete(route('tenant.roles.destroy', $owner->uuid))->assertForbidden();
        $this->delete(route('tenant.roles.destroy', $viewer->uuid))->assertForbidden();

        $custom = Role::factory()->tenantScoped($this->tenant)->create(['name' => 'Used', 'slug' => 'used']);
        TenantMembership::factory()->for($this->tenant)->for(User::factory())->for($custom)->active()->create();
        $this->delete(route('tenant.roles.destroy', $custom->uuid))->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['id' => $custom->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.deletion_blocked']);
    }

    public function test_cross_tenant_uuid_is_not_resolved_and_navigation_is_permission_aware(): void
    {
        $foreign = Role::factory()->tenantScoped()->create();
        $this->get(route('tenant.roles.show', $foreign->uuid))->assertNotFound();
        $this->get(route('tenant.dashboard'))->assertOk()->assertSee('Roles')->assertSee('Audit Logs');

        $developer = User::factory()->create(['email_verified_at' => now()]);
        $developerRole = $this->tenant->roles()->where('slug', 'developer')->firstOrFail();
        TenantMembership::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $developer->id, 'role_id' => $developerRole->id,
            'status' => MembershipStatus::Active, 'joined_at' => now(),
        ]);
        $this->actingAs($developer)->withSession(['active_tenant_id' => $this->tenant->id])
            ->get(route('tenant.dashboard'))->assertOk()->assertDontSee('>Roles<', false)->assertDontSee('Audit Logs');
        $this->get(route('tenant.roles.index'))->assertForbidden();
    }
}
