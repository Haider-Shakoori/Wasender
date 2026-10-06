<?php

namespace App\Http\Controllers;

use App\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    public function __invoke(SystemHealthService $health): JsonResponse
    {
        $checks = $health->checks();
        $public = collect($checks)->only(['database', 'cache', 'queue'])->map(fn (array $check): string => $check['healthy'] ? 'ok' : 'degraded')->all();
        $ready = ! in_array('degraded', $public, true);

        return response()->json(['status' => $ready ? 'ok' : 'degraded'] + $public, $ready ? 200 : 503);
    }
}
