<?php

namespace App\Http\Controllers;

use App\Services\PlatformDashboardQuery;
use Illuminate\View\View;

final class PlatformDashboardController extends Controller
{
    public function __invoke(PlatformDashboardQuery $query): View
    {
        return view('platform.dashboard', $query->get());
    }
}
