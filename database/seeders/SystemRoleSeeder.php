<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class SystemRoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (config('roles.templates') as $slug => $definition) {
                $role = Role::query()->updateOrCreate(
                    ['tenant_id' => null, 'slug' => $slug],
                    ['name' => $definition['name'], 'description' => "System template for {$definition['name']}.", 'is_system' => true],
                );
                $role->permissions()->sync(
                    Permission::query()->whereIn('slug', $definition['permissions'])->pluck('id'),
                );
            }
        });
    }
}
