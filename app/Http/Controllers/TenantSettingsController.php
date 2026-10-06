<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Http\Requests\UpdateTenantSettingsRequest;
use App\Services\TenantSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class TenantSettingsController extends Controller
{
    public function edit(TenantContext $context): View
    {
        $this->authorize('update', $context->get());

        return view('tenant.settings.edit', ['tenant' => $context->get(), 'timezones' => timezone_identifiers_list()]);
    }

    public function update(UpdateTenantSettingsRequest $request, TenantContext $context, TenantSettingsService $service): RedirectResponse
    {
        $service->update($context->get(), $request->user(), $request->safe()->only(['name', 'timezone', 'currency', 'locale']));

        return back()->with('status', 'Workspace settings saved.');
    }
}
