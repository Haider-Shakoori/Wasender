<?php

namespace App\Services\Billing;

use App\Contracts\BillingGateway;
use App\Models\PaymentGatewayConfig;
use InvalidArgumentException;

final class PaymentGatewayRegistry
{
    public function enabled(): array
    {
        return PaymentGatewayConfig::query()
            ->where('is_enabled', true)
            ->orderByDesc('is_default')
            ->orderBy('provider')
            ->get()
            ->map(fn (PaymentGatewayConfig $config) => [
                'provider' => $config->provider,
                'mode' => $config->mode,
                'is_default' => $config->is_default,
            ])
            ->all();
    }

    public function resolve(string $provider): BillingGateway
    {
        $config = PaymentGatewayConfig::query()
            ->where('provider', $provider)
            ->where('is_enabled', true)
            ->firstOrFail();

        return match ($provider) {
            'stripe' => new StripeBillingGateway($config),
            default => throw new InvalidArgumentException("Unsupported payment gateway [{$provider}]."),
        };
    }
}
