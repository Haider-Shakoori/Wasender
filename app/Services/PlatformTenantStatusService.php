<?php

namespace App\Services;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PlatformTenantStatusService
{
    public function __construct(private readonly PlatformAuditService $audit) {}

    public function suspend(Tenant $tenant, User $actor, string $reason): void
    {
        DB::transaction(function () use ($tenant, $actor, $reason): void {
            $tenant->update(['status' => TenantStatus::Suspended, 'is_active' => false, 'suspended_at' => now(), 'suspended_by' => $actor->id, 'suspension_reason' => $reason]);
            $this->audit->record('tenant.suspended', $actor, $tenant, ['reason' => $reason]);
        });
    }

    public function reactivate(Tenant $tenant, User $actor): void
    {
        DB::transaction(function () use ($tenant, $actor): void {
            $tenant->update(['status' => TenantStatus::Active, 'is_active' => true, 'suspended_at' => null, 'suspended_by' => null, 'suspension_reason' => null]);
            $this->audit->record('tenant.reactivated', $actor, $tenant);
        });
    }
}
