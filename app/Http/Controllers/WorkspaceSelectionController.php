<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AccessibleTenantQuery;
use App\Services\ActiveTenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class WorkspaceSelectionController extends Controller
{
    public function __invoke(
        Request $request,
        AccessibleTenantQuery $accessible,
        ActiveTenantManager $active,
    ): View|RedirectResponse {
        $memberships = $accessible->forUser($request->user());
        if ($memberships->isEmpty()) {
            return redirect()->route('tenant.no-workspace');
        }
        if ($memberships->count() === 1) {
            $active->setForUser($request->user(), $memberships->first()->tenant);

            return redirect()->route('tenant.dashboard');
        }

        return view('tenant.workspaces', ['memberships' => $memberships]);
    }
}
