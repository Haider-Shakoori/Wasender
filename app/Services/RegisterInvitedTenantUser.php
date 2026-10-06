<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invitation;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class RegisterInvitedTenantUser
{
    public function __construct(private readonly TenantInvitationService $invitations, private readonly AuditService $audit) {}

    /** @return array{user: User, membership: TenantMembership} */
    public function execute(Invitation $invitation, string $plainToken, string $name, string $password): array
    {
        return DB::transaction(function () use ($invitation, $plainToken, $name, $password): array {
            if (User::query()->where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages(['invitation' => 'An account already exists. Sign in to accept this invitation.']);
            }
            $user = User::create([
                'name' => trim($name), 'email' => $invitation->email, 'password' => Hash::make($password),
            ]);
            $membership = $this->invitations->accept($invitation, $user, $plainToken);
            $user->forceFill(['last_active_tenant_id' => $invitation->tenant_id])->save();
            $this->audit->recordDomain('user.registered_by_invitation', $user, $invitation->tenant, $user);

            return compact('user', 'membership');
        }, 3);
    }
}
