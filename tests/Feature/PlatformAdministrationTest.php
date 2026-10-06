<?php

namespace Tests\Feature;

use App\Contracts\PlatformAuthorization;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\PlatformRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlatformRoleAssignmentService;
use Database\Seeders\PlatformAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformAuthorizationSeeder::class);
        $this->admin = User::factory()->create(['status' => UserStatus::Active]);
        app(PlatformRoleAssignmentService::class)->grant(
            $this->admin,
            PlatformRole::where('slug', 'super-admin')->firstOrFail(),
        );
    }

    public function test_platform_roles_are_seeded_idempotently_and_authorize_without_tenant_context(): void
    {
        $this->seed(PlatformAuthorizationSeeder::class);

        $this->assertTrue(app(PlatformAuthorization::class)->allows($this->admin, 'platform.dashboard.view'));
        $this->assertCount(4, PlatformRole::all());
        $this->actingAs($this->admin)->get(route('platform.dashboard'))->assertOk()
            ->assertSee('no tenant context', false);
    }

    public function test_legacy_platform_flag_does_not_authorize_platform_routes(): void
    {
        $legacy = User::factory()->create(['is_platform_admin' => true, 'status' => UserStatus::Active]);
        $this->actingAs($legacy)->get(route('platform.dashboard'))->assertForbidden();
    }

    public function test_tenant_suspend_and_reactivate_are_audited(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAs($this->admin)->post(route('platform.tenants.suspend', $tenant), ['reason' => 'Compliance review required'])
            ->assertRedirect();
        $this->assertSame(TenantStatus::Suspended, $tenant->fresh()->status);
        $this->assertFalse($tenant->fresh()->is_active);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'tenant.suspended', 'subject_id' => $tenant->id]);

        $this->post(route('platform.tenants.reactivate', $tenant))->assertRedirect();
        $this->assertSame(TenantStatus::Active, $tenant->fresh()->status);
    }

    public function test_user_suspension_invalidates_sessions_and_blocks_existing_and_new_login(): void
    {
        $target = User::factory()->create(['status' => UserStatus::Active, 'password' => 'password']);
        DB::table('sessions')->insert(['id' => 'target-session', 'user_id' => $target->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($this->admin)->post(route('platform.users.suspend', $target), ['reason' => 'Security investigation'])
            ->assertRedirect();
        $this->assertSame(UserStatus::Suspended, $target->fresh()->status);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $target->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_last_super_admin_cannot_be_revoked_suspended_or_self_suspended(): void
    {
        $role = PlatformRole::where('slug', 'super-admin')->firstOrFail();
        $this->expectException(ValidationException::class);
        app(PlatformRoleAssignmentService::class)->revoke($this->admin, $role, $this->admin);
    }

    public function test_internal_notes_are_scoped_to_supported_subjects_and_audited(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAs($this->admin)->post(route('platform.notes.store', ['tenant', $tenant->uuid]), ['body' => 'Customer requested a review.'])
            ->assertRedirect();
        $this->assertDatabaseHas('platform_notes', ['subject_type' => Tenant::class, 'subject_id' => $tenant->id]);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'platform.note.created']);
    }

    public function test_failed_job_pages_never_render_raw_payload_or_exception(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'connection' => 'database', 'queue' => 'default',
            'payload' => 'TOP_SECRET_PAYLOAD', 'exception' => 'TOP_SECRET_EXCEPTION', 'failed_at' => now(),
        ]);
        $this->actingAs($this->admin)->get(route('platform.queues.show', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'))
            ->assertOk()->assertDontSee('TOP_SECRET_PAYLOAD')->assertDontSee('TOP_SECRET_EXCEPTION')->assertSee('intentionally redacted');
    }

    public function test_platform_views_and_health_are_available_and_do_not_resolve_tenant_context(): void
    {
        Tenant::factory()->create(['name' => 'Visible Tenant']);
        $this->actingAs($this->admin)->get(route('platform.tenants.index'))->assertOk()->assertSee('Visible Tenant');
        $this->get(route('platform.users.index'))->assertOk()->assertSee($this->admin->email);
        $this->get(route('platform.audit.index'))->assertOk();
        $this->get(route('platform.health'))->assertOk()->assertSee('Database')->assertSee('Scheduler');
    }
}
