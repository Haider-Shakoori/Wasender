<?php

namespace App\Http\Controllers;

use App\Enums\TenantSubscriptionStatus;
use App\Exceptions\SubscriptionTransitionException;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\PlatformTenantStatusService;
use App\Services\SubscriptionLifecycleService;
use App\Services\TenantSubscriptionAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class PlatformTenantController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $tenants = Tenant::query()->with('owner:id,name,email')->withCount('memberships')
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->latest()->paginate(20)->withQueryString();

        return view('platform.tenants.index', compact('tenants', 'search'));
    }

    public function show(Tenant $tenant, SubscriptionLifecycleService $lifecycle): View
    {
        $tenant->load(['owner:id,name,email', 'platformNotes.author:id,name', 'currentSubscription.plan'])->loadCount('memberships');
        $plans = SubscriptionPlan::where('status', 'active')->orderBy('sort_order')->get();

        $allowedSubscriptionStatuses = $tenant->currentSubscription
            ? $lifecycle->allowedTransitions($tenant->currentSubscription->status)
            : [];

        return view('platform.tenants.show', compact('tenant', 'plans', 'allowedSubscriptionStatuses'));
    }

    public function assignPlan(Request $request, Tenant $tenant, TenantSubscriptionAssignmentService $service): RedirectResponse
    {
        $data = $request->validate(['plan_uuid' => ['required', 'uuid'], 'reason' => ['required', 'string', 'min:5', 'max:500'], 'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'], 'starts_at' => ['nullable', 'date'], 'period_ends_at' => ['nullable', 'date', 'after:starts_at']]);
        $plan = SubscriptionPlan::where('uuid', $data['plan_uuid'])->firstOrFail();
        $service->assign($tenant, $plan, $request->user(), $data['reason'], (int) ($data['trial_days'] ?? 0), isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : null, isset($data['period_ends_at']) ? Carbon::parse($data['period_ends_at']) : null);

        return back()->with('status', 'Subscription plan assigned.');
    }

    public function subscriptionStatus(Request $request, Tenant $tenant, SubscriptionLifecycleService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:active,suspended,cancelled,expired'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $subscription = $tenant->currentSubscription()->firstOrFail();
        try {
            $service->transition($subscription, TenantSubscriptionStatus::from($data['status']), $data['reason'], $request->user());
        } catch (SubscriptionTransitionException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return back()->with('status', 'Subscription status updated.');
    }

    public function extendSubscription(Request $request, Tenant $tenant, SubscriptionLifecycleService $service): RedirectResponse
    {
        $data = $request->validate(['period_ends_at' => ['required', 'date', 'after:today'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $service->extendPeriod($tenant->currentSubscription()->firstOrFail(), Carbon::parse($data['period_ends_at']), $data['reason'], $request->user());

        return back()->with('status', 'Subscription period extended.');
    }

    public function suspend(Request $request, Tenant $tenant, PlatformTenantStatusService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $service->suspend($tenant, $request->user(), $data['reason']);

        return back()->with('status', 'Tenant suspended.');
    }

    public function reactivate(Request $request, Tenant $tenant, PlatformTenantStatusService $service): RedirectResponse
    {
        $service->reactivate($tenant, $request->user());

        return back()->with('status', 'Tenant reactivated.');
    }
}
