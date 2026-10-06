<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Support\Str;

final class TenantRoleSlugService
{
    public function generate(Tenant $tenant, string $name): string
    {
        $base = Str::limit(Str::slug($name), 90, '') ?: 'role-'.Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;
        while (Role::forTenant($tenant)->where('slug', $slug)->exists()) {
            $slug = Str::limit($base, 90 - strlen((string) $suffix) - 1, '').'-'.$suffix++;
        }

        return $slug;
    }
}
