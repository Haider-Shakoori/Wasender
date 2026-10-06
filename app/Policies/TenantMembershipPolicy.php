<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\TenantAuthorization;
use App\Contracts\TenantContext;
use App\Models\TenantMembership;
use App\Models\User;

final class TenantMembershipPolicy
{
    public function __construct(private TenantAuthorization $auth, private TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->auth->allows('team.view');
    }

    public function view(User $user, TenantMembership $membership): bool
    {
        return $this->current($membership) && $this->auth->allows('team.view');
    }

    public function updateRole(User $user, TenantMembership $membership): bool
    {
        return $this->mutable($membership, $user) && $this->auth->allows('team.update');
    }

    public function suspend(User $user, TenantMembership $membership): bool
    {
        return $this->mutable($membership, $user) && $this->auth->allows('team.update');
    }

    public function reactivate(User $user, TenantMembership $membership): bool
    {
        return $this->mutable($membership, $user) && $this->auth->allows('team.update');
    }

    public function remove(User $user, TenantMembership $membership): bool
    {
        return $this->mutable($membership, $user) && $this->auth->allows('team.remove');
    }

    private function current(TenantMembership $membership): bool
    {
        return $membership->tenant_id === $this->context->id();
    }

    private function mutable(TenantMembership $membership, ?User $user = null): bool
    {
        return $this->current($membership)
            && $membership->role?->slug !== 'owner'
            && (! $user || $membership->user_id !== $user->id);
    }
}
