<?php

namespace App\Http\Controllers;

use App\Models\PaymentGatewayConfig;
use App\Services\Billing\PaymentGatewayRegistry;
use App\Services\Billing\StripeWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGatewayRegistry $gateways, StripeWebhookProcessor $processor): JsonResponse
    {
        $gateway = $gateways->resolve('stripe');
        $event = $gateway->parseWebhook($request);
        $processor->handle($event);

        PaymentGatewayConfig::query()->where('provider', 'stripe')->update([
            'last_webhook_at' => now(),
            'last_webhook_status' => (string) ($event['type'] ?? 'received'),
        ]);

        return response()->json(['received' => true]);
    }
}
