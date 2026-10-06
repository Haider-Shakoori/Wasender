<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\MembershipStatus;
use App\Enums\TenantStatus;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TenantRoleFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_uuid_owner_and_enum_are_automatic_and_related(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->for($owner, 'owner')->active()->create(['uuid' => null]);

        $this->assertNotEmpty($tenant->uuid);
        $this->assertTrue($tenant->owner->is($owner));
        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertTrue($tenant->status->isActive());
    }

    public function test_tenant_slug_is_unique(): void
    {
        Tenant::factory()->create(['slug' => 'unique-company']);
        $this->expectException(QueryException::class);
        Tenant::factory()->create(['slug' => 'unique-company']);
    }

    public function test_user_can_belong_to_multiple_tenants_and_membership_relates_domain_models(): void
    {
        $user = User::factory()->create();
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();
        $firstRole = Role::factory()->tenantScoped($first)->create();
        $secondRole = Role::factory()->tenantScoped($second)->create();
        TenantMembership::factory()->for($first)->for($user)->for($firstRole)->active()->create();
        $membership = TenantMembership::factory()->for($second)->for($user)->for($secondRole)->active()->create();

        $this->assertCount(2, $user->fresh()->tenants);
        $this->assertTrue($membership->tenant->is($second));
        $this->assertTrue($membership->user->is($user));
        $this->assertTrue($membership->role->is($secondRole));
        $this->assertTrue($user->hasActiveMembership($second));
    }

    public function test_duplicate_tenant_membership_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $role = Role::factory()->tenantScoped($tenant)->create();
        TenantMembership::factory()->for($tenant)->for($user)->for($role)->create();

        $this->expectException(QueryException::class);
        TenantMembership::factory()->for($tenant)->for($user)->for($role)->create();
    }

    public function test_role_slug_is_unique_per_tenant_but_reusable_by_other_tenants(): void
    {
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();
        Role::factory()->tenantScoped($first)->create(['slug' => 'developer']);
        Role::factory()->tenantScoped($second)->create(['slug' => 'developer']);

        $this->expectException(QueryException::class);
        Role::factory()->tenantScoped($first)->create(['slug' => 'developer']);
    }

    public function test_global_role_template_slug_is_unique(): void
    {
        Role::factory()->system()->create(['slug' => 'owner']);

        $this->expectException(ValidationException::class);
        Role::factory()->system()->create(['slug' => 'owner']);
    }

    public function test_permission_slug_is_global_and_role_permission_query_is_efficient(): void
    {
        $permission = Permission::factory()->create(['slug' => 'messages.send']);
        $role = Role::factory()->system()->create();
        $role->permissions()->attach($permission);

        $this->assertTrue($role->hasPermission('messages.send'));
        $this->assertTrue($permission->roles->contains($role));

        $this->expectException(QueryException::class);
        Permission::factory()->create(['slug' => 'messages.send']);
    }

    public function test_active_and_suspended_scopes_and_membership_enum_casts_work(): void
    {
        $active = Tenant::factory()->active()->create();
        $suspended = Tenant::factory()->suspended()->create();
        $role = Role::factory()->tenantScoped($active)->create();
        $membership = TenantMembership::factory()->for($active)->for(User::factory())->for($role)->suspended()->create();

        $this->assertTrue(Tenant::active()->get()->contains($active));
        $this->assertTrue(Tenant::suspended()->get()->contains($suspended));
        $this->assertSame(MembershipStatus::Suspended, $membership->status);
        $this->assertTrue(TenantMembership::suspended()->get()->contains($membership));
    }

    public function test_audit_uuid_json_casts_and_platform_admin_boolean_work(): void
    {
        $audit = AuditLog::factory()->create(['uuid' => null, 'metadata' => ['safe' => true]]);
        $admin = User::factory()->create(['is_platform_admin' => 1]);

        $this->assertNotEmpty($audit->uuid);
        $this->assertSame(['state' => 'before'], $audit->before_values);
        $this->assertSame(['safe' => true], $audit->metadata);
        $this->assertTrue($admin->is_platform_admin);
        $this->assertTrue($admin->isPlatformAdmin());
    }
}
