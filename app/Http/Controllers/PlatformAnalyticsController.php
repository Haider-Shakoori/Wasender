<?php

namespace App\Http\Controllers;

use App\Data\Analytics\AnalyticsDateRange;
use App\Http\Requests\PlatformAnalyticsFilterRequest;
use App\Services\Analytics\PlatformWhatsAppAnalyticsService;
use Illuminate\Http\JsonResponse;

final class PlatformAnalyticsController extends Controller
{
    public function __invoke(PlatformAnalyticsFilterRequest $r, PlatformWhatsAppAnalyticsService $s): JsonResponse
    {
        return response()->json($s->report(AnalyticsDateRange::fromFilters($r->validated(), 'UTC'), $r->validated('tenant_uuid')));
    }
}
