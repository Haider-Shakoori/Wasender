<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantAuthorization;
use App\Contracts\TenantContext;
use App\Exceptions\InactiveTenantMembershipException;
use App\Exceptions\InvalidTenantRoleException;
use App\Exceptions\TenantMembershipNotFoundException;
use App\Exceptions\TenantNotResolvedException;
use App\Exceptions\TenantPermissionDeniedException;
use App\Models\Role;
use App\Models\TenantMembership;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Collection;

final class TenantAuthorizationService implements TenantAuthorization
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly TenantContext $context,
        private readonly CurrentTenantMembershipService $memberships,
        private readonly TenantPermissionCache $cache,
    ) {}

    public function allows(string $permission): bool
    {
        try {
            return $this->permissions()->contains($permission);
        } catch (TenantMembershipNotFoundException|InactiveTenantMembershipException|InvalidTenantRoleException|TenantNotResolvedException|TenantPermissionDeniedException) {
            return false;
        }
    }

    public function denies(string $permission): bool
    {
        return ! $this->allows($permission);
    }

    public function require(string $permission): void
    {
        if ($this->denies($permission)) {
            throw new TenantPermissionDeniedException;
        }
    }

    public function role(): Role
    {
        return $this->membership()->role;
    }

    public function membership(): TenantMembership
    {
        $user = $this->auth->guard()->user();
        if (! $user) {
            throw new TenantPermissionDeniedException;
        }

        return $this->memberships->get($user);
    }

    public function permissions(): Collection
    {
        $role = $this->role();

        return $this->cache->forRole($role);
    }

    public function forgetRole(Role $role): void
    {
        $this->cache->forget($role);
    }
}
