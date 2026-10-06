<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Data\Analytics\AnalyticsDateRange;
use App\Http\Requests\AnalyticsFilterRequest;
use App\Models\WhatsAppSession;
use App\Services\Analytics\AnalyticsOverviewService;
use Illuminate\Contracts\View\View;

final class AnalyticsDashboardController extends Controller
{
    public function __invoke(AnalyticsFilterRequest $request, TenantContext $context, AnalyticsOverviewService $analytics): View
    {
        $tenant = $context->get();
        $filters = $request->validated();
        $sessions = WhatsAppSession::where('tenant_id', $tenant->id)->orderBy('name')->get(['uuid', 'name']);
        $sessionUuid = $filters['session_uuid'] ?? null;
        abort_if($sessionUuid && ! $sessions->contains('uuid', $sessionUuid), 422);
        $subscription = $tenant->currentSubscription()->with('plan')->first();

        return view('tenant.analytics.index', [
            'analytics' => $analytics->report($tenant, AnalyticsDateRange::fromFilters($filters, $tenant->timezone), 10, $sessionUuid),
            'filters' => $filters,
            'sessions' => $sessions,
            'planName' => $subscription?->plan?->name ?? 'No active plan',
        ]);
    }
}
