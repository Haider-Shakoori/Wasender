<?php

namespace App\Contracts;

use App\Models\TenantSubscription;
use Illuminate\Http\Request;

interface BillingGateway
{
    public function key(): string;

    public function createCheckout(TenantSubscription $subscription, string $successUrl, string $cancelUrl): string;

    public function parseWebhook(Request $request): array;
}
