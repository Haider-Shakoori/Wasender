<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\RoleInitializer;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RolePermissionService implements RoleInitializer
{
    /** @return Collection<string, Role> */
    public function initializeForTenant(Tenant $tenant): Collection
    {
        return DB::transaction(function () use ($tenant): Collection {
            $templates = Role::query()->system()->with('permissions:id,slug')->get()->keyBy('slug');
            if ($templates->count() !== count(config('roles.templates'))) {
                throw new RuntimeException('System role templates must be seeded before tenant role initialization.');
            }

            return $templates->map(function (Role $template) use ($tenant): Role {
                $role = Role::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $template->slug],
                    [
                        'name' => $template->name,
                        'description' => $template->description,
                        'is_system' => true,
                    ],
                );
                $role->permissions()->sync($template->permissions->pluck('id'));

                return $role->refresh();
            });
        });
    }
}
