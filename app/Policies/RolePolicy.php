<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\TenantAuthorization;
use App\Contracts\TenantContext;
use App\Models\Role;
use App\Models\User;

final class RolePolicy
{
    public function __construct(private TenantAuthorization $auth, private TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->auth->allows('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->inTenant($role) && $this->auth->allows('roles.view');
    }

    public function create(User $user): bool
    {
        return $this->auth->allows('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->inTenant($role) && $role->slug !== 'owner' && $this->auth->allows('roles.manage');
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->inTenant($role) && ! $role->is_system && $role->slug !== 'owner' && $this->auth->allows('roles.manage');
    }

    public function assign(User $user, Role $role): bool
    {
        return $this->inTenant($role) && $role->slug !== 'owner' && $this->auth->allows('team.update');
    }

    private function inTenant(Role $role): bool
    {
        return $role->tenant_id === $this->context->id();
    }
}
