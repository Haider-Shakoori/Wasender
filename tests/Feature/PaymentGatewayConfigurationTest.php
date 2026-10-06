<?php

namespace Tests\Feature;

use App\Models\PaymentGatewayConfig;
use App\Services\Billing\PaymentGatewayRegistry;
use App\Services\Billing\StripeBillingGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class PaymentGatewayConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_credentials_are_encrypted_and_hidden(): void
    {
        $gateway = PaymentGatewayConfig::create([
            'provider' => 'stripe',
            'is_enabled' => true,
            'is_default' => true,
            'mode' => 'test',
            'credentials_encrypted' => [
                'secret_key' => 'sk_test_secret',
                'webhook_secret' => 'whsec_test',
            ],
        ]);

        $this->assertSame('sk_test_secret', $gateway->fresh()->credentials_encrypted['secret_key']);
        $this->assertArrayNotHasKey('credentials_encrypted', $gateway->fresh()->toArray());
        $this->assertSame('stripe', app(PaymentGatewayRegistry::class)->enabled()[0]['provider']);
    }

    public function test_stripe_webhook_signature_is_verified(): void
    {
        $config = PaymentGatewayConfig::create([
            'provider' => 'stripe',
            'is_enabled' => true,
            'mode' => 'test',
            'credentials_encrypted' => ['webhook_secret' => 'whsec_test'],
        ]);

        $payload = json_encode(['id' => 'evt_1', 'type' => 'invoice.paid', 'data' => ['object' => []]], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        $request = Request::create('/billing/webhooks/stripe', 'POST', [], [], [], [], $payload);
        $request->headers->set('Stripe-Signature', "t={$timestamp},v1={$signature}");

        $event = (new StripeBillingGateway($config))->parseWebhook($request);

        $this->assertSame('evt_1', $event['id']);
        $this->assertSame('invoice.paid', $event['type']);
    }
}
