<?php

namespace App\Http\Controllers;

use App\Services\SystemHealthService;
use Illuminate\View\View;

final class PlatformHealthController extends Controller
{
    public function __invoke(SystemHealthService $health): View
    {
        return view('platform.health', ['checks' => $health->checks()]);
    }
}
