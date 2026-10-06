<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Tenancy\CreateTenantRoleData;
use App\Data\Tenancy\UpdateTenantRoleData;
use App\Exceptions\OwnerRoleModificationException;
use App\Exceptions\ProtectedSystemRoleException;
use App\Models\Role;
use App\Models\TenantSubscription;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TenantRoleService
{
    public function __construct(
        private TenantContext $context,
        private TenantRoleSlugService $slugs,
        private TenantPermissionCache $cache,
        private AuditService $audit,
    ) {}

    public function create(CreateTenantRoleData $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $tenant = $this->context->get();
            $tenant->newQuery()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            if (TenantSubscription::query()->where('tenant_id', $tenant->id)->current()->exists()) {
                app(TenantEntitlements::class)->requireFeature('roles.manage');
                app(TenantEntitlements::class)->requireCapacity('roles.custom.max');
            }
            $role = Role::create([
                'tenant_id' => $tenant->id, 'name' => trim($data->name),
                'slug' => $this->slugs->generate($tenant, $data->name),
                'description' => $data->description, 'is_system' => false,
            ]);
            $role->permissions()->sync(array_values(array_unique($data->permissionIds)));
            $this->audit->recordDomain('role.created', auth()->user(), $tenant, $role, ['role_uuid' => $role->uuid, 'role_slug' => $role->slug]);

            return $role->refresh();
        });
    }

    public function update(Role $role, UpdateTenantRoleData $data): Role
    {
        $this->assertCurrent($role);
        if ($role->slug === 'owner') {
            throw new OwnerRoleModificationException('The Owner role is immutable.');
        }

        return DB::transaction(function () use ($role, $data): Role {
            $before = ['name' => $role->name, 'description' => $role->description, 'permissions' => $role->permissions()->pluck('slug')->all()];
            $role->update([
                'name' => $role->is_system ? $role->name : trim($data->name),
                'description' => $data->description,
            ]);
            $role->permissions()->sync(array_values(array_unique($data->permissionIds)));
            $this->cache->forget($role);
            $after = ['name' => $role->name, 'description' => $role->description, 'permissions' => $role->permissions()->pluck('slug')->all()];
            $this->audit->recordDomain('role.updated', auth()->user(), $this->context->get(), $role, ['role_uuid' => $role->uuid], $before, $after);
            if ($before['permissions'] !== $after['permissions']) {
                $this->audit->recordDomain('role.permissions_changed', auth()->user(), $this->context->get(), $role, ['role_uuid' => $role->uuid, 'changed_permission_slugs' => $after['permissions']]);
            }

            return $role->refresh();
        });
    }

    public function delete(Role $role): void
    {
        $this->assertCurrent($role);
        if ($role->is_system) {
            throw new ProtectedSystemRoleException('System roles cannot be deleted.');
        }
        $count = $role->memberships()->count();
        if ($count > 0) {
            $this->audit->recordDomain('role.deletion_blocked', auth()->user(), $this->context->get(), $role, ['role_uuid' => $role->uuid, 'assigned_member_count' => $count]);
            throw ValidationException::withMessages(['role' => 'Reassign members before deleting this role.']);
        }
        DB::transaction(function () use ($role): void {
            $this->cache->forget($role);
            $this->audit->recordDomain('role.deleted', auth()->user(), $this->context->get(), $role, ['role_uuid' => $role->uuid, 'role_slug' => $role->slug]);
            $role->delete();
        });
    }

    private function assertCurrent(Role $role): void
    {
        if ($role->tenant_id !== $this->context->id()) {
            throw new AuthorizationException;
        }
    }
}
