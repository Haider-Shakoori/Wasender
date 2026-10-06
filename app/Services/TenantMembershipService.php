<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantEntitlements;
use App\Enums\MembershipStatus;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TenantMembershipService
{
    public function __construct(private readonly TenantPermissionCache $cache, private readonly AuditService $audit) {}

    public function changeRole(Tenant $tenant, User $actor, TenantMembership $membership, Role $newRole): void
    {
        $this->assertMutable($tenant, $actor, $membership);
        if ($membership->status === MembershipStatus::Removed || $newRole->tenant_id !== $tenant->id || $newRole->slug === 'owner') {
            throw ValidationException::withMessages(['role_id' => 'This role cannot be assigned to this member.']);
        }
        DB::transaction(function () use ($tenant, $actor, $membership, $newRole): void {
            $locked = TenantMembership::query()->lockForUpdate()->findOrFail($membership->id);
            $oldRole = $locked->role()->firstOrFail();
            $this->assertAdministratorRemains($tenant, $locked, $newRole, $locked->status);
            $locked->update(['role_id' => $newRole->id]);
            $this->cache->forget($oldRole);
            $this->cache->forget($newRole);
            $this->audit->recordDomain('tenant.member_role_changed', $actor, $tenant, $locked, [
                'member_email' => $locked->user->email, 'old_role_slug' => $oldRole->slug, 'new_role_slug' => $newRole->slug,
            ]);
        }, 3);
    }

    public function suspend(Tenant $tenant, User $actor, TenantMembership $membership): void
    {
        $this->transition($tenant, $actor, $membership, MembershipStatus::Active, MembershipStatus::Suspended, 'tenant.member_suspended');
    }

    public function reactivate(Tenant $tenant, User $actor, TenantMembership $membership): void
    {
        $this->assertMutable($tenant, $actor, $membership, allowSelf: false);
        if ($membership->status !== MembershipStatus::Suspended) {
            throw ValidationException::withMessages(['member' => 'Only suspended members may be reactivated.']);
        }
        if (TenantSubscription::query()->where('tenant_id', $tenant->id)->current()->exists()) {
            app(TenantEntitlements::class)->requireCapacity('team_members.max');
        }
        $this->applyStatus($tenant, $actor, $membership, MembershipStatus::Active, 'tenant.member_reactivated');
    }

    public function remove(Tenant $tenant, User $actor, TenantMembership $membership): void
    {
        if (! in_array($membership->status, [MembershipStatus::Active, MembershipStatus::Suspended], true)) {
            throw ValidationException::withMessages(['member' => 'This membership cannot be removed.']);
        }
        $this->transition($tenant, $actor, $membership, $membership->status, MembershipStatus::Removed, 'tenant.member_removed');
    }

    private function transition(Tenant $tenant, User $actor, TenantMembership $membership, MembershipStatus $from, MembershipStatus $to, string $event): void
    {
        $this->assertMutable($tenant, $actor, $membership);
        if ($membership->status !== $from) {
            throw ValidationException::withMessages(['member' => 'This membership cannot make the requested transition.']);
        }
        $this->assertAdministratorRemains($tenant, $membership, $membership->role, $to);
        $this->applyStatus($tenant, $actor, $membership, $to, $event);
    }

    private function applyStatus(Tenant $tenant, User $actor, TenantMembership $membership, MembershipStatus $status, string $event): void
    {
        DB::transaction(function () use ($tenant, $actor, $membership, $status, $event): void {
            $old = $membership->status;
            $membership->update(['status' => $status, 'joined_at' => $membership->joined_at ?? now()]);
            $this->cache->forget($membership->role);
            if ($status !== MembershipStatus::Active && $membership->user->last_active_tenant_id === $tenant->id) {
                $membership->user->forceFill(['last_active_tenant_id' => null])->save();
            }
            $this->audit->recordDomain($event, $actor, $tenant, $membership, [
                'member_email' => $membership->user->email, 'old_status' => $old->value, 'new_status' => $status->value,
            ]);
        }, 3);
    }

    private function assertMutable(Tenant $tenant, User $actor, TenantMembership $membership, bool $allowSelf = false): void
    {
        $membership->loadMissing(['role', 'user']);
        if ($membership->tenant_id !== $tenant->id) {
            throw new AuthorizationException;
        }
        if ($membership->role->slug === 'owner' || $membership->user_id === $tenant->owner_id) {
            throw ValidationException::withMessages(['member' => 'The workspace owner cannot be modified.']);
        }
        if (! $allowSelf && $membership->user_id === $actor->id) {
            throw ValidationException::withMessages(['member' => 'You cannot perform this action on your own membership.']);
        }
    }

    private function assertAdministratorRemains(Tenant $tenant, TenantMembership $target, Role $resultingRole, MembershipStatus $resultingStatus): void
    {
        $targetWasAdmin = $target->status === MembershipStatus::Active
            && $target->role->hasPermission('team.update') && $target->role->hasPermission('roles.manage');
        $targetRemainsAdmin = $resultingStatus === MembershipStatus::Active
            && $resultingRole->hasPermission('team.update') && $resultingRole->hasPermission('roles.manage');
        if (! $targetWasAdmin || $targetRemainsAdmin) {
            return;
        }
        $others = TenantMembership::query()->active()->where('tenant_id', $tenant->id)->whereKeyNot($target->id)
            ->whereHas('role', fn ($query) => $query->where('slug', '!=', 'owner'))
            ->whereHas('role.permissions', fn ($query) => $query->where('slug', 'team.update'))
            ->whereHas('role.permissions', fn ($query) => $query->where('slug', 'roles.manage'))->exists();
        if (! $others) {
            throw ValidationException::withMessages(['member' => 'The workspace must retain an administrator.']);
        }
    }
}
