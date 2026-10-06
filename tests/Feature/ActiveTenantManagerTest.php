<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ActiveTenantResolutionStatus;
use App\Enums\MembershipStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\ActiveTenantManager;
use App\Services\RolePermissionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ActiveTenantManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_resolution_uses_valid_session_then_remembered_then_fallback(): void
    {
        $user = User::factory()->create();
        [$first] = $this->membership($user);
        [$second] = $this->membership($user);
        $manager = app(ActiveTenantManager::class);

        session(['active_tenant_id' => $second->id]);
        $this->assertTrue($manager->resolveForUser($user)->is($second));
        $manager->clear();
        $user->update(['last_active_tenant_id' => $first->id]);
        $this->assertTrue($manager->resolveForUser($user->fresh())->is($first));
        $manager->clear();
        $user->update(['last_active_tenant_id' => null]);
        $this->assertSame(
            ActiveTenantResolutionStatus::SelectionRequired,
            $manager->resolve($user->fresh())->status,
        );
    }

    public function test_invalid_foreign_suspended_removed_and_deleted_tenants_are_ignored(): void
    {
        $user = User::factory()->create();
        $foreign = Tenant::factory()->create();
        [$suspendedTenant, $suspended] = $this->membership($user, MembershipStatus::Suspended);
        [$removedTenant] = $this->membership($user, MembershipStatus::Removed);
        [$deletedTenant] = $this->membership($user);
        $deletedTenant->delete();
        session(['active_tenant_id' => $foreign->id]);
        $user->update(['last_active_tenant_id' => $suspendedTenant->id]);

        $this->assertNull(app(ActiveTenantManager::class)->resolveForUser($user->fresh()));
        $this->assertFalse(session()->has('active_tenant_id'));
        $this->assertSame(MembershipStatus::Suspended, $suspended->status);
        $this->assertNotNull($removedTenant);
    }

    /** @return array{Tenant,TenantMembership} */
    private function membership(User $user, MembershipStatus $status = MembershipStatus::Active): array
    {
        $tenant = Tenant::factory()->create();
        $role = app(RolePermissionService::class)->initializeForTenant($tenant)->get('viewer');
        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id, 'user_id' => $user->id, 'role_id' => $role->id,
            'status' => $status, 'joined_at' => $status === MembershipStatus::Active ? now() : null,
        ]);

        return [$tenant, $membership];
    }
}
