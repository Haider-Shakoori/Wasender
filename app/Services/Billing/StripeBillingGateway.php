<?php

namespace App\Services\Billing;

use App\Contracts\BillingGateway;
use App\Models\PaymentGatewayConfig;
use App\Models\TenantSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class StripeBillingGateway implements BillingGateway
{
    public function __construct(private PaymentGatewayConfig $config) {}

    public function key(): string
    {
        return 'stripe';
    }

    public function createCheckout(TenantSubscription $subscription, string $successUrl, string $cancelUrl): string
    {
        $subscription->loadMissing(['tenant', 'plan']);
        $credentials = (array) $this->config->credentials_encrypted;
        $secret = (string) ($credentials['secret_key'] ?? '');

        if ($secret === '') {
            throw new RuntimeException('stripe_not_configured');
        }

        $interval = $subscription->plan->billing_interval->value === 'yearly' ? 'year' : 'month';
        $response = Http::asForm()
            ->withToken($secret)
            ->timeout(20)
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'subscription',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => $subscription->tenant->uuid,
                'customer_email' => $subscription->tenant->email,
                'metadata[tenant_uuid]' => $subscription->tenant->uuid,
                'metadata[subscription_uuid]' => $subscription->uuid,
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => strtolower($subscription->plan->price_currency),
                'line_items[0][price_data][unit_amount]' => (int) $subscription->plan->price_amount,
                'line_items[0][price_data][product_data][name]' => $subscription->plan->name,
                'line_items[0][price_data][recurring][interval]' => $interval,
            ])
            ->throw()
            ->json();

        $url = (string) ($response['url'] ?? '');
        if ($url === '') {
            throw new RuntimeException('stripe_checkout_url_missing');
        }

        return $url;
    }

    public function parseWebhook(Request $request): array
    {
        $credentials = (array) $this->config->credentials_encrypted;
        $secret = (string) ($credentials['webhook_secret'] ?? '');
        if ($secret === '') {
            throw new RuntimeException('stripe_webhook_not_configured');
        }

        $header = (string) $request->header('Stripe-Signature');
        $parts = collect(explode(',', $header))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

                return $key && $value ? [$key => $value] : [];
            });

        $timestamp = (string) $parts->get('t', '');
        $signature = (string) $parts->get('v1', '');
        if ($timestamp === '' || $signature === '' || abs(time() - (int) $timestamp) > 300) {
            throw new RuntimeException('stripe_webhook_signature_invalid');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('stripe_webhook_signature_invalid');
        }

        return json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
