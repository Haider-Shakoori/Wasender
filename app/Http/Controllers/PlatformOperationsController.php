<?php

namespace App\Http\Controllers;

use App\Services\PlatformOperationsService;
use Illuminate\View\View;

final class PlatformOperationsController extends Controller
{
    public function __invoke(PlatformOperationsService $operations): View
    {
        return view('platform.operations', $operations->summary());
    }
}
