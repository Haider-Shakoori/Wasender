<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantContext;
use App\Enums\TenantInvitationStatus;
use App\Models\Invitation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class TenantInvitationQuery
{
    public function __construct(private readonly TenantContext $context) {}

    public function paginate(?string $search = null, ?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        Invitation::query()->where('tenant_id', $this->context->id())
            ->where('status', TenantInvitationStatus::Pending)->where('expires_at', '<', now())
            ->update(['status' => TenantInvitationStatus::Expired]);

        return Invitation::query()->where('tenant_id', $this->context->id())->with(['inviter', 'role'])
            ->when($status && TenantInvitationStatus::tryFrom($status), fn ($query) => $query->where('status', $status))
            ->when($search, fn ($query) => $query->where('email', 'like', "%{$search}%"))
            ->latest()->paginate($perPage)->withQueryString();
    }

    public function findByUuid(string $uuid): Invitation
    {
        return Invitation::query()->where('tenant_id', $this->context->id())->with(['inviter', 'role'])
            ->where('uuid', $uuid)->firstOrFail();
    }
}
