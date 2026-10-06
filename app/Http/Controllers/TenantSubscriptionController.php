<?php

namespace App\Http\Controllers;

use App\Services\TenantSubscriptionQuery;
use Illuminate\View\View;

final class TenantSubscriptionController extends Controller
{
    public function __invoke(TenantSubscriptionQuery $query): View
    {
        return view('tenant.subscription.show', $query->overview());
    }
}
