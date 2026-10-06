<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

final class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('roles.permissions') as $slug) {
            Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => str($slug)->replace(['.', '_'], ' ')->title(), 'description' => config("roles.descriptions.{$slug}")],
            );
        }
    }
}
