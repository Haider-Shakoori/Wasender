<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantContext;
use App\Data\Tenancy\TenantSwitchResult;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;

final class TenantSwitchService
{
    public function __construct(
        private readonly AccessibleTenantQuery $accessible,
        private readonly ActiveTenantManager $active,
        private readonly TenantContext $context,
        private readonly AuditService $audit,
        private readonly Session $session,
    ) {}

    public function switch(User $user, Tenant $tenant): TenantSwitchResult
    {
        $membership = $this->accessible->findForUser($user, $tenant);
        if (! $membership || ! $membership->role) {
            throw new AuthorizationException('You do not have access to that workspace.');
        }

        $previousMembership = is_numeric($this->session->get(ActiveTenantManager::SESSION_KEY))
            ? $this->accessible->findForUser($user, (int) $this->session->get(ActiveTenantManager::SESSION_KEY))
            : null;
        $previous = $previousMembership?->tenant;

        DB::transaction(function () use ($user, $tenant, $membership, $previous): void {
            $user->forceFill(['last_active_tenant_id' => $tenant->id])->save();
            $this->audit->recordDomain('tenant.switched', $user, $tenant, $tenant, [
                'from_tenant_uuid' => $previous?->uuid,
                'to_tenant_uuid' => $tenant->uuid,
                'to_role_slug' => $membership->role->slug,
                'selection_source' => 'explicit_switch',
            ]);
        });

        $this->active->setForUser($user, $tenant);
        $this->context->set($tenant);
        $this->session->regenerate();

        return new TenantSwitchResult($tenant, $membership, $previous);
    }
}
