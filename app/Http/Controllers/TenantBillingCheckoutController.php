<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Services\Billing\PaymentGatewayRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class TenantBillingCheckoutController extends Controller
{
    public function __invoke(Request $request, TenantContext $context, PaymentGatewayRegistry $gateways): RedirectResponse
    {
        $data = $request->validate(['provider' => ['required', 'string', 'in:stripe']]);
        $subscription = $context->get()->currentSubscription()->with(['tenant', 'plan'])->first();

        if (! $subscription || ! $subscription->plan || ! $subscription->plan->price_amount) {
            throw ValidationException::withMessages(['provider' => 'This subscription is not eligible for online checkout.']);
        }

        $url = $gateways->resolve($data['provider'])->createCheckout(
            $subscription,
            route('tenant.billing.show', ['payment' => 'success']),
            route('tenant.billing.show', ['payment' => 'cancelled']),
        );

        return redirect()->away($url);
    }
}
