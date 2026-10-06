<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\AccessibleTenantQuery;
use App\Services\RolePermissionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccessibleTenantQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_returns_only_accessible_user_memberships_with_roles_loaded(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $current = $this->membership($user, 'Zulu');
        $alpha = $this->membership($user, 'Alpha');
        $this->membership($user, 'Invited', MembershipStatus::Invited);
        $this->membership($user, 'Suspended tenant', tenantStatus: TenantStatus::Suspended);
        $foreign = Tenant::factory()->create(['name' => 'Foreign']);

        $results = app(AccessibleTenantQuery::class)->forUser($user, $current->id);

        $this->assertSame([$current->id, $alpha->id], $results->pluck('tenant_id')->all());
        $this->assertTrue($results->every(fn (TenantMembership $membership) => $membership->relationLoaded('tenant') && $membership->relationLoaded('role')));
        $this->assertFalse($results->pluck('tenant_id')->contains($foreign->id));
    }

    private function membership(
        User $user,
        string $name,
        MembershipStatus $status = MembershipStatus::Active,
        TenantStatus $tenantStatus = TenantStatus::Active,
    ): Tenant {
        $tenant = Tenant::factory()->create(['name' => $name, 'status' => $tenantStatus, 'is_active' => $tenantStatus === TenantStatus::Active]);
        $role = app(RolePermissionService::class)->initializeForTenant($tenant)->get('viewer');
        TenantMembership::create([
            'tenant_id' => $tenant->id, 'user_id' => $user->id, 'role_id' => $role->id,
            'status' => $status, 'joined_at' => now(),
        ]);

        return $tenant;
    }
}
