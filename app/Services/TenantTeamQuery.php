<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantContext;
use App\Enums\MembershipStatus;
use App\Models\TenantMembership;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class TenantTeamQuery
{
    public function __construct(private readonly TenantContext $context) {}

    public function paginate(?string $search = null, ?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        return TenantMembership::query()->forTenant($this->context->id())->with(['user', 'role.permissions'])
            ->when($status && MembershipStatus::tryFrom($status), fn ($query) => $query->where('status', $status))
            ->when($search, fn ($query) => $query->whereHas('user', fn ($users) => $users
                ->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderByRaw("CASE WHEN EXISTS (SELECT 1 FROM roles WHERE roles.id = tenant_user.role_id AND roles.slug = 'owner') THEN 0 WHEN status = 'active' THEN 1 WHEN status = 'suspended' THEN 2 ELSE 3 END")
            ->orderByRaw('(SELECT name FROM users WHERE users.id = tenant_user.user_id)')
            ->paginate($perPage)->withQueryString();
    }

    public function counts(): array
    {
        return TenantMembership::query()->forTenant($this->context->id())
            ->selectRaw('status, COUNT(*) total')->groupBy('status')->pluck('total', 'status')->all();
    }

    public function findByUuid(string $uuid): TenantMembership
    {
        return TenantMembership::query()->forTenant($this->context->id())->with(['user', 'role.permissions'])
            ->where('uuid', $uuid)->firstOrFail();
    }
}
