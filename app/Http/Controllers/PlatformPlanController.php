<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Services\SubscriptionPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformPlanController extends Controller
{
    public function index(): View
    {
        return view('platform.plans.index', ['plans' => SubscriptionPlan::withCount('subscriptions')->orderBy('sort_order')->paginate(20)]);
    }

    public function create(): View
    {
        return view('platform.plans.form', ['plan' => new SubscriptionPlan]);
    }

    public function edit(SubscriptionPlan $plan): View
    {
        $plan->load('features');

        return view('platform.plans.form', compact('plan'));
    }

    public function show(SubscriptionPlan $plan): View
    {
        $plan->load('features')->loadCount('subscriptions');

        return view('platform.plans.show', compact('plan'));
    }

    public function store(Request $r, SubscriptionPlanService $s): RedirectResponse
    {
        $plan = $s->save($this->validated($r), $r->user());

        return redirect()->route('platform.plans.show', $plan)->with('status', 'Plan created.');
    }

    public function update(Request $r, SubscriptionPlan $plan, SubscriptionPlanService $s): RedirectResponse
    {
        $s->save($this->validated($r), $r->user(), $plan);

        return back()->with('status', 'Plan updated.');
    }

    private function validated(Request $r): array
    {
        return $r->validate(['name' => ['required', 'string', 'max:100'], 'slug' => ['nullable', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:1000'], 'status' => ['required', 'in:draft,active,archived'], 'billing_interval' => ['required', 'in:monthly,yearly'], 'price_amount' => ['nullable', 'integer', 'min:0'], 'price_currency' => ['nullable', 'string', 'size:3'], 'trial_days' => ['required', 'integer', 'min:0', 'max:365'], 'grace_days' => ['required', 'integer', 'min:0', 'max:90'], 'sort_order' => ['nullable', 'integer', 'min:0'], 'is_public' => ['nullable', 'boolean'], 'is_featured' => ['nullable', 'boolean'], 'features' => ['array'], 'features.*' => ['in:'.implode(',', array_keys(config('subscriptions.features')))], 'limits' => ['array'], 'limits.*' => ['nullable', 'integer', 'min:0'], 'limit_unlimited' => ['array']]);
    }
}
