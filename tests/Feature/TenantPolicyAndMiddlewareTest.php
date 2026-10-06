<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\TenantContext;
use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\RolePermissionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class TenantPolicyAndMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_permission_middleware_allows_viewer_roles_and_denies_developer_consistently(): void
    {
        [$viewer] = $this->member('viewer');
        $this->actingAs($viewer)->get(route('tenant.roles.index'))->assertOk();

        [$developer] = $this->member('developer');
        $this->actingAs($developer)->get(route('tenant.roles.index'))->assertForbidden();
        $this->withHeaders(['Accept' => 'application/json'])
            ->get(route('tenant.roles.index'))
            ->assertForbidden()->assertJsonPath('error.code', 'TENANT_PERMISSION_DENIED');
    }

    public function test_tenant_role_and_audit_policies_are_tenant_scoped(): void
    {
        [$user, $tenant] = $this->member('owner');
        $foreign = Tenant::factory()->create();
        $custom = Role::factory()->tenantScoped($tenant)->create();
        $system = $tenant->roles()->where('slug', 'viewer')->firstOrFail();
        $audit = AuditLog::factory()->create(['tenant_id' => $tenant->id]);
        $foreignAudit = AuditLog::factory()->create(['tenant_id' => $foreign->id]);
        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $this->assertTrue(Gate::forUser($user)->allows('view', $tenant));
        $this->assertFalse(Gate::forUser($user)->allows('view', $foreign));
        $this->assertTrue(Gate::forUser($user)->allows('update', $custom));
        $this->assertTrue(Gate::forUser($user)->allows('delete', $custom));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $system));
        $this->assertTrue(Gate::forUser($user)->allows('view', $audit));
        $this->assertFalse(Gate::forUser($user)->allows('view', $foreignAudit));
    }

    /** @return array{User,Tenant} */
    private function member(string $roleSlug): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $tenant = Tenant::factory()->for($user, 'owner')->create();
        $role = app(RolePermissionService::class)->initializeForTenant($tenant)->get($roleSlug);
        TenantMembership::create([
            'tenant_id' => $tenant->id, 'user_id' => $user->id, 'role_id' => $role->id,
            'status' => MembershipStatus::Active, 'joined_at' => now(),
        ]);
        $user->update(['last_active_tenant_id' => $tenant->id]);

        return [$user, $tenant];
    }
}
