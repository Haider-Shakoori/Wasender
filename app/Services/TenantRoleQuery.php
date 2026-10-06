<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantContext;
use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class TenantRoleQuery
{
    public function __construct(private TenantContext $context) {}

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Role::forTenant($this->context->id())
            ->withCount(['permissions', 'memberships'])
            ->orderByRaw("CASE WHEN slug = 'owner' THEN 0 WHEN is_system = 1 THEN 1 ELSE 2 END")
            ->orderBy('name')->paginate($perPage);
    }

    public function findByUuid(string $uuid): Role
    {
        return Role::forTenant($this->context->id())
            ->with(['permissions'])->withCount('memberships')
            ->where('uuid', $uuid)->firstOrFail();
    }
}
