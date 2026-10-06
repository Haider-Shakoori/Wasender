<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantEntitlements;
use App\Data\Tenancy\CreateTenantInvitationData;
use App\Enums\MembershipStatus;
use App\Enums\TenantInvitationStatus;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class TenantInvitationService
{
    public function __construct(
        private readonly InvitationTokenService $tokens,
        private readonly AuditService $audit,
    ) {}

    public function create(Tenant $tenant, User $actor, CreateTenantInvitationData $data): Invitation
    {
        $email = mb_strtolower(trim($data->email));

        return DB::transaction(function () use ($tenant, $actor, $data, $email): Invitation {
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            if (TenantSubscription::query()->where('tenant_id', $tenant->id)->current()->exists()) {
                app(TenantEntitlements::class)->requireFeature('team.manage');
                app(TenantEntitlements::class)->requireCapacity('pending_invitations.max');
                app(TenantEntitlements::class)->requireCapacity('team_members.max');
            }
            $role = $this->assignableRole($tenant, $data->roleId);
            $membership = TenantMembership::query()->where('tenant_id', $tenant->id)
                ->whereHas('user', fn ($query) => $query->where('email', $email))->lockForUpdate()->first();
            if ($membership?->status === MembershipStatus::Active) {
                throw ValidationException::withMessages(['email' => 'This person is already an active member.']);
            }
            if ($membership?->status === MembershipStatus::Suspended) {
                throw ValidationException::withMessages(['email' => 'This member is suspended. Reactivate their membership instead.']);
            }
            if (Invitation::query()->where('tenant_id', $tenant->id)->where('email', $email)
                ->where('status', TenantInvitationStatus::Pending)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['email' => 'A pending invitation already exists for this email address.']);
            }

            $token = $this->tokens->generate();
            $invitation = Invitation::create([
                'tenant_id' => $tenant->id,
                'email' => $email,
                'role_id' => $role->id,
                'token_hash' => $token->hash,
                'status' => TenantInvitationStatus::Pending,
                'expires_at' => now()->addHours(config('saas.tenant_invitation_expiry_hours')),
                'invited_by' => $actor->id,
                'last_sent_at' => now(),
                'send_count' => 1,
            ]);
            $this->audit->recordDomain('tenant.member_invited', $actor, $tenant, $invitation, [
                'invitation_uuid' => $invitation->uuid, 'member_email' => $email, 'role_slug' => $role->slug,
            ]);
            $this->dispatchAfterCommit($invitation, $token->plain);

            return $invitation;
        }, 3);
    }

    public function resend(Invitation $invitation, Tenant $tenant, User $actor): Invitation
    {
        $this->assertTenant($invitation, $tenant);

        return DB::transaction(function () use ($invitation, $tenant, $actor): Invitation {
            $locked = Invitation::query()->lockForUpdate()->findOrFail($invitation->id);
            $this->expireIfNecessary($locked);
            if ($locked->status !== TenantInvitationStatus::Pending) {
                throw ValidationException::withMessages(['invitation' => 'Only pending invitations may be resent.']);
            }
            $token = $this->tokens->generate();
            $locked->update([
                'token_hash' => $token->hash,
                'expires_at' => now()->addHours(config('saas.tenant_invitation_expiry_hours')),
                'last_sent_at' => now(),
                'send_count' => $locked->send_count + 1,
            ]);
            $this->audit->recordDomain('tenant.invitation_resent', $actor, $tenant, $locked, ['invitation_uuid' => $locked->uuid]);
            $this->dispatchAfterCommit($locked, $token->plain);

            return $locked->refresh();
        }, 3);
    }

    public function revoke(Invitation $invitation, Tenant $tenant, User $actor): void
    {
        $this->assertTenant($invitation, $tenant);
        DB::transaction(function () use ($invitation, $tenant, $actor): void {
            $locked = Invitation::query()->lockForUpdate()->findOrFail($invitation->id);
            $this->expireIfNecessary($locked);
            if ($locked->status !== TenantInvitationStatus::Pending) {
                throw ValidationException::withMessages(['invitation' => 'Only pending invitations may be revoked.']);
            }
            $locked->update([
                'status' => TenantInvitationStatus::Revoked, 'revoked_at' => now(), 'revoked_by' => $actor->id,
            ]);
            $this->audit->recordDomain('tenant.invitation_revoked', $actor, $tenant, $locked, ['invitation_uuid' => $locked->uuid]);
        }, 3);
    }

    public function resolve(string $uuid, string $plainToken): Invitation
    {
        $invitation = Invitation::with(['tenant', 'role', 'inviter'])->where('uuid', $uuid)->first();
        if (! $invitation || ! $this->tokens->matches($plainToken, $invitation->token_hash)) {
            throw ValidationException::withMessages(['invitation' => 'This invitation is invalid or no longer available.']);
        }
        $this->expireIfNecessary($invitation);
        if ($invitation->status !== TenantInvitationStatus::Pending) {
            throw ValidationException::withMessages(['invitation' => 'This invitation is invalid or no longer available.']);
        }

        return $invitation;
    }

    public function accept(Invitation $invitation, User $user, string $plainToken): TenantMembership
    {
        if (mb_strtolower($user->email) !== $invitation->email) {
            throw new AuthorizationException('Sign in with the email address that received this invitation.');
        }

        return DB::transaction(function () use ($invitation, $user, $plainToken): TenantMembership {
            $locked = Invitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if (! $this->tokens->matches($plainToken, $locked->token_hash)
                || $locked->status !== TenantInvitationStatus::Pending || $locked->expires_at->isPast()) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is invalid or no longer available.']);
            }
            $membership = TenantMembership::query()->where('tenant_id', $locked->tenant_id)
                ->where('user_id', $user->id)->lockForUpdate()->first();
            if ($membership && $membership->status === MembershipStatus::Suspended) {
                throw ValidationException::withMessages(['invitation' => 'This membership is suspended and must be reactivated by an administrator.']);
            }
            $membership ??= new TenantMembership(['tenant_id' => $locked->tenant_id, 'user_id' => $user->id]);
            $membership->fill(['role_id' => $locked->role_id, 'status' => MembershipStatus::Active]);
            $membership->joined_at ??= now();
            $membership->save();
            $locked->update(['status' => TenantInvitationStatus::Accepted, 'accepted_at' => now()]);
            $this->audit->recordDomain('tenant.invitation_accepted', $user, $locked->tenant, $locked, [
                'invitation_uuid' => $locked->uuid, 'member_email' => $locked->email,
            ]);

            return $membership;
        }, 3);
    }

    public function expireIfNecessary(Invitation $invitation): void
    {
        if ($invitation->status === TenantInvitationStatus::Pending && $invitation->expires_at->isPast()) {
            $invitation->update(['status' => TenantInvitationStatus::Expired]);
        }
    }

    private function assignableRole(Tenant $tenant, int $roleId): Role
    {
        $role = Role::forTenant($tenant)->find($roleId);
        if (! $role || $role->slug === 'owner') {
            throw ValidationException::withMessages(['role_id' => 'Select an assignable role from this workspace.']);
        }

        return $role;
    }

    private function assertTenant(Invitation $invitation, Tenant $tenant): void
    {
        if ($invitation->tenant_id !== $tenant->id) {
            throw new AuthorizationException;
        }
    }

    private function dispatchAfterCommit(Invitation $invitation, string $plainToken): void
    {
        $invitation->loadMissing(['tenant', 'role', 'inviter']);
        $notification = new TenantInvitationNotification(
            $invitation->inviter->name,
            $invitation->tenant->name,
            $invitation->role->name,
            $invitation->expires_at->toDayDateTimeString(),
            route('invitations.show', ['invitationUuid' => $invitation->uuid, 'token' => $plainToken]),
        );
        DB::afterCommit(fn () => Notification::route('mail', $invitation->email)->notify($notification));
    }
}
