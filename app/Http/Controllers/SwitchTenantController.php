<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SwitchTenantRequest;
use App\Models\Tenant;
use App\Services\TenantSwitchService;
use Illuminate\Http\RedirectResponse;

final class SwitchTenantController extends Controller
{
    public function __invoke(
        SwitchTenantRequest $request,
        Tenant $tenant,
        TenantSwitchService $service,
    ): RedirectResponse {
        $result = $service->switch($request->user(), $tenant);

        return redirect($request->safeRedirect() ?? route('tenant.dashboard'))
            ->with('status', "Workspace changed to {$result->tenant->name}.");
    }
}
