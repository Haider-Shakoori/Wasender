<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Services\RolePermissionService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RolePermissionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_and_system_role_seeders_are_idempotent(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(SystemRoleSeeder::class);
        $this->seed(SystemRoleSeeder::class);

        $this->assertSame(count(config('roles.permissions')), Permission::count());
        $this->assertSame(count(config('roles.templates')), Role::system()->count());
    }

    public function test_each_template_has_the_declared_permission_matrix(): void
    {
        $this->seed([PermissionSeeder::class, SystemRoleSeeder::class]);

        foreach (config('roles.templates') as $slug => $definition) {
            $actual = Role::system()->where('slug', $slug)->firstOrFail()
                ->permissions()->pluck('slug')->sort()->values()->all();
            $expected = collect($definition['permissions'])->sort()->values()->all();
            $this->assertSame($expected, $actual, "Unexpected permission matrix for {$slug}.");
        }
        $this->assertSame(
            Permission::query()->orderBy('slug')->pluck('slug')->all(),
            Role::system()->where('slug', 'owner')->firstOrFail()->permissions()->orderBy('slug')->pluck('slug')->all(),
        );
    }

    public function test_role_initialization_is_tenant_scoped_idempotent_and_correct(): void
    {
        $this->seed([PermissionSeeder::class, SystemRoleSeeder::class]);
        $tenant = Tenant::factory()->create();
        $service = app(RolePermissionService::class);

        $first = $service->initializeForTenant($tenant);
        $second = $service->initializeForTenant($tenant);

        $this->assertSame(count(config('roles.templates')), $first->count());
        $this->assertSame(count(config('roles.templates')), $tenant->roles()->count());
        $this->assertTrue($second->every(fn (Role $role) => $role->tenant_id === $tenant->id && $role->is_system));
        foreach (config('roles.templates') as $slug => $definition) {
            $actual = $tenant->roles()->where('slug', $slug)->firstOrFail()
                ->permissions()->pluck('slug')->sort()->values()->all();
            $this->assertSame(collect($definition['permissions'])->sort()->values()->all(), $actual);
        }
    }
}
