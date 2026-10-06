<?php

namespace App\Http\Controllers;

use App\Data\Analytics\AnalyticsDateRange;
use App\Http\Requests\PlatformAnalyticsFilterRequest;
use App\Services\Analytics\PlatformWhatsAppAnalyticsService;
use Illuminate\Contracts\View\View;

final class PlatformAnalyticsDashboardController extends Controller
{
    public function __invoke(PlatformAnalyticsFilterRequest $request, PlatformWhatsAppAnalyticsService $analytics): View
    {
        $filters = $request->validated();

        return view('platform.analytics.index', [
            'analytics' => $analytics->report(AnalyticsDateRange::fromFilters($filters, 'UTC'), $filters['tenant_uuid'] ?? null),
            'filters' => $filters,
        ]);
    }
}
