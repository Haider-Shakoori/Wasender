<?php

namespace App\Services;

use App\Models\PlatformRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class PlatformRoleAssignmentService
{
    public function __construct(private readonly PlatformAuthorizationService $authorization, private readonly PlatformAuditService $audit) {}

    public function grant(User $user, PlatformRole $role, ?User $actor = null): void
    {
        $user->platformRoles()->syncWithoutDetaching([$role->id]);
        $this->authorization->forget($user);
        $this->audit->record('platform.role.granted', $actor, $user, ['role' => $role->slug]);
    }

    public function revoke(User $user, PlatformRole $role, ?User $actor = null): void
    {
        if ($role->slug === config('platform.super_admin_role') && $this->activeSuperAdminCount() <= 1 && $user->isActive()) {
            throw ValidationException::withMessages(['role' => 'The last active Super Admin cannot be revoked.']);
        }
        $user->platformRoles()->detach($role);
        $this->authorization->forget($user);
        $this->audit->record('platform.role.revoked', $actor, $user, ['role' => $role->slug]);
    }

    public function activeSuperAdminCount(): int
    {
        return User::query()->where('status', 'active')->whereHas('platformRoles',
            fn ($query) => $query->where('slug', config('platform.super_admin_role'))
        )->count();
    }
}
