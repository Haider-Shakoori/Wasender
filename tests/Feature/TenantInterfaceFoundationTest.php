<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TenantInvitationStatus;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\RolePermissionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TenantInterfaceFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->tenant = Tenant::factory()->for($this->owner, 'owner')->create(['name' => 'Interface Studio']);
        $ownerRole = app(RolePermissionService::class)->initializeForTenant($this->tenant)->get('owner');
        TenantMembership::factory()->for($this->tenant)->for($this->owner)->for($ownerRole)->active()->create();
        $this->owner->update(['last_active_tenant_id' => $this->tenant->id]);
        $this->actingAs($this->owner)->withSession(['active_tenant_id' => $this->tenant->id]);
    }

    public function test_tenant_shell_has_accessible_navigation_theme_and_account_context(): void
    {
        $this->get(route('tenant.dashboard'))->assertOk()
            ->assertSee('Skip to main content')
            ->assertSee('aria-label="Open navigation"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Change appearance')
            ->assertSee('data-theme="system"', false)
            ->assertSee("['light','dark','system']", false)
            ->assertSee($this->tenant->name)
            ->assertSee($this->owner->name)
            ->assertSee('Owner')
            ->assertSee('method="post"', false)
            ->assertSee('WhatsApp sessions')
            ->assertSee('Later')
            ->assertDontSee('href="#"', false);
    }

    public function test_dashboard_uses_only_real_current_tenant_metrics(): void
    {
        $viewer = $this->tenant->roles()->where('slug', 'viewer')->firstOrFail();
        TenantMembership::factory()->for($this->tenant)->for(User::factory())->for($viewer)->active()->create();
        Invitation::factory()->create([
            'tenant_id' => $this->tenant->id, 'role_id' => $viewer->id, 'invited_by' => $this->owner->id,
            'status' => TenantInvitationStatus::Pending,
        ]);
        Role::factory()->tenantScoped($this->tenant)->create(['is_system' => false]);
        $foreignOwner = User::factory()->create();
        $foreign = Tenant::factory()->for($foreignOwner, 'owner')->create();
        $foreignRoles = app(RolePermissionService::class)->initializeForTenant($foreign);
        TenantMembership::factory()->for($foreign)->for(User::factory())->for($foreignRoles->get('viewer'))->active()->create();

        $this->get(route('tenant.dashboard'))->assertOk()
            ->assertSeeInOrder(['Team members', '2', 'Pending invites', '1', 'Custom roles', '1'])
            ->assertDontSee($foreign->name);
    }

    public function test_settings_update_validates_safe_fields_and_audits(): void
    {
        $this->put(route('tenant.settings.update'), [
            'name' => 'Renamed Studio', 'timezone' => 'Asia/Kabul', 'currency' => 'USD', 'locale' => 'en',
            'owner_id' => 999, 'status' => 'suspended', 'is_active' => false,
        ])->assertSessionHasErrors(['owner_id', 'status', 'is_active']);
        $this->assertSame('Interface Studio', $this->tenant->fresh()->name);

        $this->put(route('tenant.settings.update'), [
            'name' => 'Renamed Studio', 'timezone' => 'Asia/Kabul', 'currency' => 'AFN', 'locale' => 'en',
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id, 'name' => 'Renamed Studio', 'timezone' => 'Asia/Kabul', 'currency' => 'AFN']);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $this->tenant->id, 'action' => 'tenant.settings_updated']);
    }

    public function test_settings_and_audit_routes_reject_missing_permissions(): void
    {
        $viewer = User::factory()->create(['email_verified_at' => now()]);
        $role = $this->tenant->roles()->where('slug', 'developer')->firstOrFail();
        TenantMembership::factory()->for($this->tenant)->for($viewer)->for($role)->active()->create();
        $this->actingAs($viewer)->withSession(['active_tenant_id' => $this->tenant->id]);
        $this->get(route('tenant.settings.edit'))->assertForbidden();
        $this->get(route('tenant.audit.index'))->assertForbidden();
    }

    public function test_audit_interface_is_filterable_and_tenant_isolated(): void
    {
        $local = AuditLog::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id, 'action' => 'tenant.settings_updated']);
        $foreignOwner = User::factory()->create();
        $foreign = Tenant::factory()->for($foreignOwner, 'owner')->create();
        $foreignLog = AuditLog::factory()->create(['tenant_id' => $foreign->id, 'action' => 'foreign.secret']);

        $this->get(route('tenant.audit.index', ['action' => 'tenant.settings_updated']))
            ->assertOk()->assertSee('Tenant Settings Updated')->assertDontSee('Foreign Secret');
        $this->get(route('tenant.audit.show', $local->uuid))->assertOk()->assertSee($local->uuid);
        $this->get(route('tenant.audit.show', $foreignLog->uuid))->assertNotFound();
    }

    public function test_dashboard_and_major_pages_keep_bounded_query_counts(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('tenant.dashboard'))->assertOk();
        $dashboardQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(30, $dashboardQueries);

        DB::flushQueryLog();
        $this->get(route('tenant.team.index'))->assertOk();
        $teamQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(35, $teamQueries);
    }
}
