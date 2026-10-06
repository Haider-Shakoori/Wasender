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

final class LoginTenantIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_login_restores_valid_tenant_and_writes_audit(): void
    {
        [$user, $tenant] = $this->member();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('tenant.dashboard'));
        $this->assertSame($tenant->id, session('active_tenant_id'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.logged_in', 'user_id' => $user->id]);
    }

    public function test_login_rejects_unauthorized_remembered_tenant_and_falls_back(): void
    {
        [$user, $tenant] = $this->member();
        $foreign = Tenant::factory()->create();
        $user->update(['last_active_tenant_id' => $foreign->id]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertSame($tenant->id, session('active_tenant_id'));
    }

    public function test_logout_clears_active_tenant_and_writes_audit(): void
    {
        [$user, $tenant] = $this->member();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertFalse(session()->has('active_tenant_id'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.logged_out', 'user_id' => $user->id]);
    }

    /** @return array{User,Tenant} */
    private function member(): array
    {
        $user = User::factory()->create(['password' => 'password', 'email_verified_at' => now()]);
        $tenant = Tenant::factory()->for($user, 'owner')->create();
        $role = app(RolePermissionService::class)->initializeForTenant($tenant)->get('owner');
        TenantMembership::create([
            'tenant_id' => $tenant->id, 'user_id' => $user->id, 'role_id' => $role->id,
            'status' => MembershipStatus::Active, 'joined_at' => now(),
        ]);
        $user->update(['last_active_tenant_id' => $tenant->id]);

        return [$user, $tenant];
    }
}
