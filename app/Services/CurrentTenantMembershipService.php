<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantContext;
use App\Enums\MembershipStatus;
use App\Exceptions\InactiveTenantMembershipException;
use App\Exceptions\InvalidTenantRoleException;
use App\Exceptions\TenantMembershipNotFoundException;
use App\Models\Role;
use App\Models\TenantMembership;
use App\Models\User;

final class CurrentTenantMembershipService
{
    public function __construct(private readonly TenantContext $context) {}

    public function forUser(User $user): ?TenantMembership
    {
        return $user->tenantMemberships()
            ->active()
            ->where('tenant_id', $this->context->id())
            ->with('role.permissions')
            ->first();
    }

    public function get(User $user): TenantMembership
    {
        $membership = $user->tenantMemberships()
            ->where('tenant_id', $this->context->id())
            ->with('role.permissions')
            ->first();
        if (! $membership) {
            throw new TenantMembershipNotFoundException('No membership exists for the active workspace.');
        }
        if ($membership->status !== MembershipStatus::Active) {
            throw new InactiveTenantMembershipException('The active workspace membership is unavailable.');
        }
        if (! $membership->role || $membership->role->tenant_id !== $this->context->id()) {
            throw new InvalidTenantRoleException('The active membership role is invalid.');
        }

        return $membership;
    }

    public function role(User $user): Role
    {
        return $this->get($user)->role;
    }

    public function roleSlug(User $user): string
    {
        return $this->role($user)->slug;
    }

    public function isOwner(User $user): bool
    {
        return $this->roleSlug($user) === 'owner';
    }
}
