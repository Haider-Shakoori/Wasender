<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\TenantStatus;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\RolePermissionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_active_member_switches_by_uuid_and_persistence_drives_next_dashboard(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        [$from] = $this->membership($user, name: 'First Workspace', role: 'viewer');
        [$to] = $this->membership($user, name: 'Second Workspace', role: 'developer');
        $user->update(['last_active_tenant_id' => $from->id]);

        $response = $this->actingAs($user)->withSession(['active_tenant_id' => $from->id])
            ->post(route('tenant.switch', $to));

        $response->assertRedirect(route('tenant.dashboard'))
            ->assertSessionHas('status', 'Workspace changed to Second Workspace.');
        $this->assertSame($to->id, session('active_tenant_id'));
        $this->assertSame($to->id, $user->fresh()->last_active_tenant_id);
        $this->get(route('tenant.dashboard'))->assertOk()
            ->assertSee('Second Workspace')
            ->assertSee('Developer')
            ->assertSee('First Workspace'); // Accessible alternatives remain visible only in the switcher.

        $audit = AuditLog::where('action', 'tenant.switched')->sole();
        $this->assertSame($from->uuid, $audit->metadata['from_tenant_uuid']);
        $this->assertSame($to->uuid, $audit->metadata['to_tenant_uuid']);
        $this->assertSame('developer', $audit->metadata['to_role_slug']);
        $this->assertArrayNotHasKey('session_id', $audit->metadata);

        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('tenant.dashboard'));
        $this->assertSame($to->id, session('active_tenant_id'));
    }

    public function test_inaccessible_memberships_and_tenant_states_cannot_switch(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        [$current] = $this->membership($user);
        $user->update(['last_active_tenant_id' => $current->id]);

        foreach ([MembershipStatus::Invited, MembershipStatus::Suspended, MembershipStatus::Removed] as $status) {
            [$target] = $this->membership($user, status: $status);
            $this->actingAs($user)->withSession(['active_tenant_id' => $current->id])
                ->post(route('tenant.switch', $target))->assertForbidden();
            $this->assertSame($current->id, session('active_tenant_id'));
            $this->assertSame($current->id, $user->fresh()->last_active_tenant_id);
        }

        foreach ([TenantStatus::Suspended, TenantStatus::Pending, TenantStatus::Cancelled] as $status) {
            [$target] = $this->membership($user, tenantStatus: $status);
            $this->actingAs($user)->withSession(['active_tenant_id' => $current->id])
                ->post(route('tenant.switch', $target))->assertForbidden();
        }
        $this->assertDatabaseMissing('audit_logs', ['action' => 'tenant.switched']);
    }

    public function test_foreign_platform_admin_and_deleted_tenant_are_not_switchable(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_platform_admin' => true]);
        [$current] = $this->membership($user);
        $foreign = Tenant::factory()->create();
        $deleted = Tenant::factory()->create();
        $deleted->delete();

        $this->actingAs($user)->withSession(['active_tenant_id' => $current->id])
            ->post(route('tenant.switch', $foreign))->assertForbidden();
        $this->post('/app/workspaces/'.$deleted->uuid.'/switch')->assertNotFound();
        $this->assertSame($current->id, session('active_tenant_id'));
    }

    public function test_redirect_is_local_allowlisted_and_attack_values_are_rejected(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        [$from] = $this->membership($user);
        [$to] = $this->membership($user);

        $this->actingAs($user)->withSession(['active_tenant_id' => $from->id])
            ->post(route('tenant.switch', $to), ['redirect_to' => '/app/onboarding?from=switch'])
            ->assertRedirect('/app/onboarding?from=switch');

        foreach (['https://evil.test', '//evil.test', 'javascript:alert(1)', '/app/workspaces', 'not-a-path'] as $unsafe) {
            $this->withSession(['active_tenant_id' => $to->id])
                ->post(route('tenant.switch', $from), ['redirect_to' => $unsafe])
                ->assertSessionHasErrors('redirect_to');
            $this->assertSame($to->id, session('active_tenant_id'));
        }
    }

    /** @return array{Tenant,TenantMembership} */
    private function membership(
        User $user,
        MembershipStatus $status = MembershipStatus::Active,
        TenantStatus $tenantStatus = TenantStatus::Active,
        string $name = 'Workspace',
        string $role = 'viewer',
    ): array {
        $tenant = Tenant::factory()->for($user, 'owner')->create([
            'name' => $name.' '.fake()->unique()->numerify('###'),
            'status' => $tenantStatus,
            'is_active' => $tenantStatus === TenantStatus::Active,
        ]);
        if (str_starts_with($name, 'First') || str_starts_with($name, 'Second')) {
            $tenant->update(['name' => $name]);
        }
        $tenantRole = app(RolePermissionService::class)->initializeForTenant($tenant)->get($role);
        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id, 'user_id' => $user->id, 'role_id' => $tenantRole->id,
            'status' => $status, 'joined_at' => $status === MembershipStatus::Active ? now() : null,
        ]);

        return [$tenant, $membership];
    }
}
