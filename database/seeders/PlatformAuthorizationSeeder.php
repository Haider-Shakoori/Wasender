<?php

namespace Database\Seeders;

use App\Models\PlatformPermission;
use App\Models\PlatformRole;
use Illuminate\Database\Seeder;

final class PlatformAuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('platform.permissions') as $slug => $name) {
            PlatformPermission::query()->updateOrCreate(['slug' => $slug], ['name' => $name]);
        }
        foreach (config('platform.roles') as $slug => $definition) {
            $role = PlatformRole::query()->updateOrCreate(['slug' => $slug], [
                'name' => $definition['name'], 'description' => $definition['name'].' platform role', 'is_system' => true,
            ]);
            $slugs = $definition['permissions'] === ['*'] ? array_keys(config('platform.permissions')) : $definition['permissions'];
            $role->permissions()->sync(PlatformPermission::query()->whereIn('slug', $slugs)->pluck('id'));
        }
    }
}
