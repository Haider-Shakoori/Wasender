<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TenantSettingsService
{
    public function __construct(private readonly AuditService $audit) {}

    public function update(Tenant $tenant, User $actor, array $values): Tenant
    {
        return DB::transaction(function () use ($tenant, $actor, $values): Tenant {
            $before = $tenant->only(['name', 'timezone', 'currency', 'locale']);
            $tenant->update($values);
            $this->audit->recordDomain('tenant.settings_updated', $actor, $tenant, $tenant, [], $before, $tenant->only(array_keys($before)));

            return $tenant->refresh();
        });
    }
}
