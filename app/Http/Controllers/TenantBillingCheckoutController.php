<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Models\SubscriptionPlan;
use App\Services\Billing\PaymentGatewayRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class TenantBillingCheckoutController extends Controller
{
    public function __invoke(Request $request, TenantContext $context, PaymentGatewayRegistry $gateways): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'in:stripe'],
            'plan_uuid' => ['required', 'uuid'],
        ]);
        $subscription = $context->get()->currentSubscription()->with('tenant')->first();
        $plan = SubscriptionPlan::query()
            ->where('uuid', $data['plan_uuid'])
            ->where('status', 'active')
            ->where('is_public', true)
            ->first();

        if (! $subscription || ! $plan || ! $plan->price_amount) {
            throw ValidationException::withMessages(['plan_uuid' => 'The selected plan is not available for online checkout.']);
        }

        $url = $gateways->resolve($data['provider'])->createCheckout(
            $subscription,
            $plan,
            route('tenant.billing.show', ['payment' => 'success']),
            route('tenant.billing.show', ['payment' => 'cancelled']),
        );

        return redirect()->away($url);
    }
}
