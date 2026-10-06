<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\RecordBillingPaymentService;
use App\Services\SubscriptionUsageRegistry;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformBillingController extends Controller
{
    public function index(Request $request, SubscriptionUsageRegistry $usage): View
    {
        $plan = (string) $request->query('plan');
        $status = (string) $request->query('status');
        $scope = (string) $request->query('scope');
        $tenants = Tenant::query()->with(['currentSubscription.plan.features'])
            ->when($plan, fn ($q) => $q->whereHas('currentSubscription.plan', fn ($p) => $p->where('slug', $plan)))
            ->when($status, fn ($q) => $q->whereHas('currentSubscription', fn ($s) => $s->where('status', $status)))
            ->when($scope === 'expiring', fn ($q) => $q->whereHas('currentSubscription', fn ($s) => $s->whereBetween('current_period_ends_at', [now(), now()->addDays(14)])))
            ->orderBy('name')->paginate(20)->withQueryString();
        $tenants->getCollection()->transform(function (Tenant $tenant) use ($usage): Tenant {
            $tenant->setAttribute('billing_usage', $usage->summary($tenant, 'messages.monthly'));
            $tenant->setRelation('latestBillingPayment', $tenant->billingPayments()->latest('paid_at')->first());

            return $tenant;
        });
        if ($scope === 'high_usage') {
            $tenants->setCollection($tenants->getCollection()->filter(fn (Tenant $tenant) => ! $tenant->billing_usage['unlimited'] && $tenant->billing_usage['percentage'] >= 80)->values());
        }

        return view('platform.billing.index', ['tenants' => $tenants, 'plans' => SubscriptionPlan::orderBy('sort_order')->get(), 'planFilter' => $plan, 'statusFilter' => $status, 'scopeFilter' => $scope]);
    }

    public function show(Tenant $tenant, SubscriptionUsageRegistry $usage, SubscriptionLifecycleService $lifecycle): View
    {
        $tenant->load(['currentSubscription.plan.features', 'currentSubscription.history']);

        return view('platform.billing.show', [
            'tenant' => $tenant,
            'plans' => SubscriptionPlan::where('status', 'active')->orderBy('sort_order')->get(),
            'messageUsage' => $usage->summary($tenant, 'messages.monthly'),
            'payments' => $tenant->billingPayments()->with('recorder:id,name')->latest('paid_at')->paginate(15),
            'allowedSubscriptionStatuses' => $tenant->currentSubscription
                ? $lifecycle->allowedTransitions($tenant->currentSubscription->status)
                : [],
        ]);
    }

    public function payment(Request $request, Tenant $tenant, RecordBillingPaymentService $service): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'reference' => ['nullable', 'string', 'max:120'],
            'paid_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $service->record($tenant, $data, $request->user());

        return back()->with('status', 'Manual payment recorded.');
    }
}
