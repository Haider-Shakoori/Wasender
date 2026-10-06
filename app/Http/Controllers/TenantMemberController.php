<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Http\Requests\UpdateTenantMemberRoleRequest;
use App\Models\Role;
use App\Services\TenantMembershipService;
use App\Services\TenantTeamQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TenantMemberController extends Controller
{
    public function role(UpdateTenantMemberRoleRequest $request, string $membershipUuid, TenantContext $context, TenantTeamQuery $query, TenantMembershipService $service): RedirectResponse
    {
        $membership = $query->findByUuid($membershipUuid);
        $this->authorize('updateRole', $membership);
        $role = Role::forTenant($context->id())->findOrFail($request->integer('role_id'));
        $service->changeRole($context->get(), $request->user(), $membership, $role);

        return back()->with('status', 'Member role updated.');
    }

    public function suspend(Request $request, string $membershipUuid, TenantContext $context, TenantTeamQuery $query, TenantMembershipService $service): RedirectResponse
    {
        $membership = $query->findByUuid($membershipUuid);
        $this->authorize('suspend', $membership);
        $service->suspend($context->get(), $request->user(), $membership);

        return back()->with('status', 'Member suspended.');
    }

    public function reactivate(Request $request, string $membershipUuid, TenantContext $context, TenantTeamQuery $query, TenantMembershipService $service): RedirectResponse
    {
        $membership = $query->findByUuid($membershipUuid);
        $this->authorize('reactivate', $membership);
        $service->reactivate($context->get(), $request->user(), $membership);

        return back()->with('status', 'Member reactivated.');
    }

    public function destroy(Request $request, string $membershipUuid, TenantContext $context, TenantTeamQuery $query, TenantMembershipService $service): RedirectResponse
    {
        $membership = $query->findByUuid($membershipUuid);
        $this->authorize('remove', $membership);
        $service->remove($context->get(), $request->user(), $membership);

        return back()->with('status', 'Member removed.');
    }
}
