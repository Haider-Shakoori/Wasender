<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\TenantAuthorization;
use App\Contracts\TenantContext;
use App\Models\Invitation;
use App\Models\User;

final class TenantInvitationPolicy
{
    public function __construct(private readonly TenantAuthorization $auth, private readonly TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->auth->allows('team.view');
    }

    public function create(User $user): bool
    {
        return $this->auth->allows('team.invite');
    }

    public function resend(User $user, Invitation $invitation): bool
    {
        return $this->current($invitation) && $this->auth->allows('team.invite');
    }

    public function revoke(User $user, Invitation $invitation): bool
    {
        return $this->current($invitation) && $this->auth->allows('team.invite');
    }

    private function current(Invitation $invitation): bool
    {
        return $invitation->tenant_id === $this->context->id();
    }
}
