<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlatformUserStatusService
{
    public function __construct(private readonly PlatformRoleAssignmentService $roles, private readonly PlatformAuditService $audit) {}

    public function suspend(User $user, User $actor, string $reason): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['user' => 'You cannot suspend your own account.']);
        }
        if ($user->platformRoles()->where('slug', config('platform.super_admin_role'))->exists() && $this->roles->activeSuperAdminCount() <= 1) {
            throw ValidationException::withMessages(['user' => 'The last active Super Admin cannot be suspended.']);
        }
        DB::transaction(function () use ($user, $actor, $reason): void {
            $user->update(['status' => UserStatus::Suspended, 'suspended_at' => now(), 'suspended_by' => $actor->id, 'suspension_reason' => $reason]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $this->audit->record('user.suspended', $actor, $user, ['reason' => $reason]);
        });
    }

    public function reactivate(User $user, User $actor): void
    {
        $user->update(['status' => UserStatus::Active, 'suspended_at' => null, 'suspended_by' => null, 'suspension_reason' => null]);
        $this->audit->record('user.reactivated', $actor, $user);
    }
}
