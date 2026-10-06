<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformIntegrationController extends Controller
{
    public function __invoke(Request $request): View
    {
        $q = Integration::with('tenant:id,uuid,name')->withCount(['events as recent_failure_count' => fn ($q) => $q->where('status', 'failed')->where('created_at', '>=', now()->subDays(30))])->when($request->string('search')->trim()->toString(), fn ($q, $v) => $q->where(fn ($q) => $q->where('name', 'like', '%'.addcslashes($v, '%_').'%')->orWhereHas('tenant', fn ($t) => $t->where('name', 'like', '%'.addcslashes($v, '%_').'%'))));

        return view('platform.integrations.index', ['integrations' => $q->latest('updated_at')->paginate(25)->withQueryString()]);
    }
}
