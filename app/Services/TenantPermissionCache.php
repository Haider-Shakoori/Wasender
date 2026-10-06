<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;

final class TenantPermissionCache
{
    public function __construct(private readonly Repository $cache) {}

    /** @return Collection<int, string> */
    public function forRole(Role $role): Collection
    {
        return collect($this->cache->rememberForever(
            $this->key($role),
            fn () => $role->permissions()->orderBy('slug')->pluck('slug')->all(),
        ));
    }

    public function forget(Role $role): void
    {
        if ($role->tenant) {
            $this->cache->forget($this->key($role));
        }
    }

    public function key(Role $role): string
    {
        return "tenant-auth:{$role->tenant->uuid}:role:{$role->id}:permissions";
    }
}
