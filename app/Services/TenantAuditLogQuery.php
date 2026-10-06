<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantContext;
use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class TenantAuditLogQuery
{
    public function __construct(private readonly TenantContext $context) {}

    public function paginate(?string $search, ?string $action, int $perPage = 25): LengthAwarePaginator
    {
        return AuditLog::query()->where('tenant_id', $this->context->id())->with('user')
            ->when($search, fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('action', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($users) => $users->where('name', 'like', "%{$search}%"));
            }))
            ->when($action, fn ($query) => $query->where('action', $action))
            ->latest('created_at')->paginate($perPage)->withQueryString();
    }

    public function actions(): Collection
    {
        return AuditLog::query()->where('tenant_id', $this->context->id())
            ->distinct()->orderBy('action')->pluck('action');
    }

    public function findByUuid(string $uuid): AuditLog
    {
        return AuditLog::query()->where('tenant_id', $this->context->id())->with('user')
            ->where('uuid', $uuid)->firstOrFail();
    }
}
