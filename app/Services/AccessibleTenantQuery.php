<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Collection;

final class AccessibleTenantQuery
{
    /** @return Collection<int, TenantMembership> */
    public function forUser(User $user, ?int $currentTenantId = null): Collection
    {
        return $user->tenantMemberships()
            ->where('status', MembershipStatus::Active)
            ->whereHas('tenant', fn ($query) => $query
                ->where('status', TenantStatus::Active)
                ->where('is_active', true))
            ->with(['tenant', 'role:id,tenant_id,name,slug'])
            ->limit(50)
            ->get()
            ->filter(fn (TenantMembership $membership) => $membership->role?->tenant_id === $membership->tenant_id)
            ->sortBy(fn (TenantMembership $membership) => [
                $membership->tenant_id === $currentTenantId ? 0 : 1,
                mb_strtolower($membership->tenant->name),
            ])
            ->values();
    }

    public function findForUser(User $user, Tenant|int $tenant): ?TenantMembership
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        $membership = $user->tenantMemberships()
            ->where('status', MembershipStatus::Active)
            ->where('tenant_id', $tenantId)
            ->whereHas('tenant', fn ($query) => $query
                ->where('status', TenantStatus::Active)
                ->where('is_active', true))
            ->with(['tenant', 'role:id,tenant_id,name,slug'])
            ->first();

        return $membership?->role?->tenant_id === $membership?->tenant_id ? $membership : null;
    }
}
