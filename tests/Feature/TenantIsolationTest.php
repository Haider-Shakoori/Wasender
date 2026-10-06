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

final class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manipulated_session_tenant_falls_back_without_exposing_other_tenant(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $own = Tenant::factory()->for($user, 'owner')->create(['name' => 'Own']);
        $foreign = Tenant::factory()->create(['name' => 'Secret Foreign']);
        $role = app(RolePermissionService::class)->initializeForTenant($own)->get('owner');
        TenantMembership::create([
            'tenant_id' => $own->id, 'user_id' => $user->id, 'role_id' => $role->id,
            'status' => MembershipStatus::Active, 'joined_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $foreign->id])
            ->get(route('tenant.dashboard'));

        $response->assertOk()->assertSee('Own')->assertDontSee('Secret Foreign');
        $this->assertSame($own->id, session('active_tenant_id'));
    }

    public function test_non_member_with_only_foreign_remembered_tenant_sees_no_workspace(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['email_verified_at' => now(), 'last_active_tenant_id' => $tenant->id]);

        $this->actingAs($user)->get(route('tenant.dashboard'))
            ->assertRedirect(route('tenant.no-workspace'));
    }
}
