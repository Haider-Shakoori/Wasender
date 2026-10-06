<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Tenancy\ActiveTenantResolution;
use App\Enums\ActiveTenantResolutionStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

final class ActiveTenantManager
{
    public const SESSION_KEY = 'active_tenant_id';

    private ?string $source = null;

    public function __construct(
        private readonly Session $session,
        private readonly AccessibleTenantQuery $accessible,
    ) {}

    public function setForUser(User $user, Tenant $tenant): void
    {
        abort_unless($this->accessibleTenant($user, $tenant->id), 403);
        $this->select($user, $tenant, 'explicit');
    }

    public function resolve(User $user): ActiveTenantResolution
    {
        $sessionValue = $this->session->get(self::SESSION_KEY);
        if (is_numeric($sessionValue) && ($tenant = $this->accessibleTenant($user, (int) $sessionValue))) {
            return $this->resolved($user, $tenant, 'session');
        }
        if ($sessionValue !== null) {
            $this->clear();
        }

        if ($user->last_active_tenant_id && ($tenant = $this->accessibleTenant($user, $user->last_active_tenant_id))) {
            return $this->resolved($user, $tenant, 'remembered');
        }
        if ($user->last_active_tenant_id) {
            $user->forceFill(['last_active_tenant_id' => null])->save();
        }

        $memberships = $this->accessible->forUser($user);
        if ($memberships->count() === 1) {
            return $this->resolved($user, $memberships->first()->tenant, 'single_fallback');
        }
        if ($memberships->count() > 1) {
            return new ActiveTenantResolution(ActiveTenantResolutionStatus::SelectionRequired);
        }

        return new ActiveTenantResolution(ActiveTenantResolutionStatus::NoAccessibleTenant);
    }

    public function resolveForUser(User $user): ?Tenant
    {
        return $this->resolve($user)->tenant;
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->source = null;
    }

    public function sessionKey(): string
    {
        return self::SESSION_KEY;
    }

    public function source(): ?string
    {
        return $this->source;
    }

    private function accessibleTenant(User $user, int $tenantId): ?Tenant
    {
        return $this->accessible->findForUser($user, $tenantId)?->tenant;
    }

    private function resolved(User $user, Tenant $tenant, string $source): ActiveTenantResolution
    {
        $this->select($user, $tenant, $source);

        return new ActiveTenantResolution(ActiveTenantResolutionStatus::Resolved, $tenant, $source);
    }

    private function select(User $user, Tenant $tenant, string $source): void
    {
        $this->session->put(self::SESSION_KEY, $tenant->id);
        if ($user->last_active_tenant_id !== $tenant->id) {
            $user->forceFill(['last_active_tenant_id' => $tenant->id])->save();
        }
        $this->source = $source;
    }
}
