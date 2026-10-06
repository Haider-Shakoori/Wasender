<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Data\Tenancy\CreateTenantInvitationData;
use App\Http\Requests\StoreTenantInvitationRequest;
use App\Models\Invitation;
use App\Models\Role;
use App\Services\TenantInvitationQuery;
use App\Services\TenantInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TenantInvitationController extends Controller
{
    public function index(Request $request, TenantContext $context, TenantInvitationQuery $query): View
    {
        $this->authorize('viewAny', Invitation::class);

        return view('tenant.team.invitations', [
            'invitations' => $query->paginate($request->string('search')->toString(), $request->string('status')->toString()),
            'roles' => Role::forTenant($context->id())->where('slug', '!=', 'owner')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreTenantInvitationRequest $request, TenantContext $context, TenantInvitationService $service): RedirectResponse
    {
        $service->create($context->get(), $request->user(), new CreateTenantInvitationData(
            $request->string('email')->toString(), $request->integer('role_id')
        ));

        return back()->with('status', 'Invitation sent successfully.');
    }

    public function resend(string $invitationUuid, Request $request, TenantContext $context, TenantInvitationQuery $query, TenantInvitationService $service): RedirectResponse
    {
        $invitation = $query->findByUuid($invitationUuid);
        $this->authorize('resend', $invitation);
        $service->resend($invitation, $context->get(), $request->user());

        return back()->with('status', 'Invitation resent. The previous link is no longer valid.');
    }

    public function revoke(string $invitationUuid, Request $request, TenantContext $context, TenantInvitationQuery $query, TenantInvitationService $service): RedirectResponse
    {
        $invitation = $query->findByUuid($invitationUuid);
        $this->authorize('revoke', $invitation);
        $service->revoke($invitation, $context->get(), $request->user());

        return back()->with('status', 'Invitation revoked.');
    }
}
