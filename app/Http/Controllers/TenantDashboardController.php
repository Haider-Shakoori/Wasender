<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Services\CurrentTenantMembershipService;
use App\Services\TenantDashboardQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TenantDashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $context, CurrentTenantMembershipService $memberships, TenantDashboardQuery $dashboard): View
    {
        $membership = $memberships->forUser($request->user());

        return view('tenant.dashboard', [
            'tenant' => $context->get(),
            'membership' => $membership,
            'memberCount' => $context->get()->memberships()->active()->count(),
            ...$dashboard->summary(),
        ]);
    }
}
