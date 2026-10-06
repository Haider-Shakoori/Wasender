<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\TenantAuthorization;
use App\Contracts\TenantContext;
use App\Enums\MembershipStatus;
use App\Exceptions\TenantPermissionDeniedException;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\RolePermissionService;
use App\Services\TenantPermissionCache;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantAuthorizationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_permissions_come_only_from_active_current_tenant_membership(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_platform_admin' => true]);
        [$first, $firstMembership] = $this->membership($user, 'viewer');
        [$second] = $this->membership($user, 'developer');
        $this->actingAs($user);

        app(TenantContext::class)->set($first);
        $auth = app(TenantAuthorization::class);
        $this->assertTrue($auth->allows('roles.view'));
        $this->assertFalse($auth->allows('messages.send'));
        $this->assertTrue($auth->denies('roles.manage'));
        $this->assertSame('viewer', $auth->role()->slug);

        app(TenantContext::class)->set($second);
        $this->assertTrue($auth->allows('messages.send'));
        $this->assertFalse($auth->allows('roles.view'));

        $firstMembership->update(['status' => MembershipStatus::Suspended]);
        app(TenantContext::class)->set($first);
        $this->assertFalse($auth->allows('roles.view'));
        $this->expectException(TenantPermissionDeniedException::class);
        $auth->require('roles.view');
    }

    public function test_platform_admin_without_membership_has_no_tenant_permissions(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenant = Tenant::factory()->create();
        $this->actingAs($admin);
        app(TenantContext::class)->set($tenant);
        $this->assertFalse(app(TenantAuthorization::class)->allows('tenant.update'));
    }

    public function test_role_cache_is_tenant_specific_and_explicitly_invalidated(): void
    {
        $user = User::factory()->create();
        [$tenant, $membership] = $this->membership($user, 'viewer');
        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);
        $auth = app(TenantAuthorization::class);
        $this->assertFalse($auth->allows('roles.manage'));

        $permission = Permission::where('slug', 'roles.manage')->firstOrFail();
        $membership->role->permissions()->attach($permission);
        $this->assertFalse($auth->allows('roles.manage'), 'Cached permissions must remain stable until invalidated.');
        app(TenantPermissionCache::class)->forget($membership->role);
        $this->assertTrue($auth->allows('roles.manage'));
        $this->assertStringContainsString($tenant->uuid, app(TenantPermissionCache::class)->key($membership->role));

        $developer = $tenant->roles()->where('slug', 'developer')->firstOrFail();
        $membership->update(['role_id' => $developer->id]);
        $this->assertTrue($auth->allows('messages.send'));
        $this->assertFalse($auth->allows('roles.view'));
    }

    /** @return array{Tenant,TenantMembership} */
    private function membership(User $user, string $roleSlug): array
    {
        $tenant = Tenant::factory()->for($user, 'owner')->create();
        $role = app(RolePermissionService::class)->initializeForTenant($tenant)->get($roleSlug);
        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id, 'user_id' => $user->id, 'role_id' => $role->id,
            'status' => MembershipStatus::Active, 'joined_at' => now(),
        ]);

        return [$tenant, $membership];
    }
}
