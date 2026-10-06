<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Tenancy\CreateTenantRoleData;
use App\Data\Tenancy\UpdateTenantRoleData;
use App\Http\Requests\StoreTenantRoleRequest;
use App\Http\Requests\UpdateTenantRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\TenantRoleQuery;
use App\Services\TenantRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class TenantRoleController extends Controller
{
    public function index(TenantRoleQuery $query): View
    {
        $this->authorize('viewAny', Role::class);

        return view('tenant.roles.index', ['roles' => $query->paginate()]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('tenant.roles.create', ['groups' => $this->permissionGroups()]);
    }

    public function store(StoreTenantRoleRequest $request, TenantRoleService $service): RedirectResponse
    {
        $role = $service->create(new CreateTenantRoleData(
            (string) $request->string('name'), $request->string('description')->toString() ?: null,
            array_map('intval', $request->validated('permissions')),
        ));

        return redirect()->route('tenant.roles.show', $role->uuid)->with('status', 'Role created.');
    }

    public function show(string $roleUuid, TenantRoleQuery $query): View
    {
        $role = $query->findByUuid($roleUuid);
        $this->authorize('view', $role);

        return view('tenant.roles.show', compact('role'));
    }

    public function edit(string $roleUuid, TenantRoleQuery $query): View
    {
        $role = $query->findByUuid($roleUuid);
        $this->authorize('update', $role);

        return view('tenant.roles.edit', ['role' => $role, 'groups' => $this->permissionGroups()]);
    }

    public function update(UpdateTenantRoleRequest $request, string $roleUuid, TenantRoleQuery $query, TenantRoleService $service): RedirectResponse
    {
        $role = $query->findByUuid($roleUuid);
        $service->update($role, new UpdateTenantRoleData(
            (string) $request->string('name'), $request->string('description')->toString() ?: null,
            array_map('intval', $request->validated('permissions')),
        ));

        return redirect()->route('tenant.roles.show', $role->uuid)->with('status', 'Role updated.');
    }

    public function destroy(string $roleUuid, TenantRoleQuery $query, TenantRoleService $service): RedirectResponse
    {
        $role = $query->findByUuid($roleUuid);
        $this->authorize('delete', $role);
        $service->delete($role);

        return redirect()->route('tenant.roles.index')->with('status', 'Role deleted.');
    }

    private function permissionGroups(): array
    {
        $permissions = Permission::query()->whereIn('slug', config('roles.permissions'))->get()->keyBy('slug');

        return collect(config('roles.groups'))->map(
            fn (array $slugs) => collect($slugs)->map(fn (string $slug) => $permissions->get($slug))->filter()->values(),
        )->all();
    }
}
