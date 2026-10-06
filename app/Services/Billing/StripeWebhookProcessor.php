<?php

namespace App\Services\Billing;

use App\Enums\TenantSubscriptionStatus;
use App\Models\BillingPayment;
use App\Models\TenantSubscription;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Support\Facades\DB;

final class StripeWebhookProcessor
{
    public function __construct(private SubscriptionLifecycleService $lifecycle) {}

    public function handle(array $event): void
    {
        $type = (string) ($event['type'] ?? '');
        $object = (array) data_get($event, 'data.object', []);
        $eventId = (string) ($event['id'] ?? '');

        match ($type) {
            'checkout.session.completed' => $this->checkoutCompleted($object),
            'invoice.paid' => $this->invoicePaid($eventId, $object),
            'invoice.payment_failed' => $this->paymentFailed($object),
            default => null,
        };
    }

    private function checkoutCompleted(array $object): void
    {
        $uuid = (string) data_get($object, 'metadata.subscription_uuid', '');
        if ($uuid === '') {
            return;
        }

        TenantSubscription::query()->where('uuid', $uuid)->update([
            'provider' => 'stripe',
            'provider_subscription_id' => $object['subscription'] ?? null,
        ]);
    }

    private function invoicePaid(string $eventId, array $object): void
    {
        $providerSubscriptionId = (string) ($object['subscription'] ?? '');
        if ($providerSubscriptionId === '') {
            return;
        }

        DB::transaction(function () use ($eventId, $object, $providerSubscriptionId): void {
            $subscription = TenantSubscription::query()
                ->with(['tenant', 'plan'])
                ->where('provider', 'stripe')
                ->where('provider_subscription_id', $providerSubscriptionId)
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                return;
            }

            BillingPayment::query()->firstOrCreate(
                ['reference' => $eventId],
                [
                    'tenant_id' => $subscription->tenant_id,
                    'subscription_id' => $subscription->id,
                    'amount' => (int) ($object['amount_paid'] ?? 0),
                    'currency' => strtoupper((string) ($object['currency'] ?? $subscription->plan->price_currency)),
                    'payment_method' => 'stripe',
                    'status' => 'paid',
                    'paid_at' => now(),
                    'notes' => 'Stripe invoice payment',
                ],
            );

            $periodEnd = data_get($object, 'lines.data.0.period.end');
            $subscription->forceFill([
                'current_period_starts_at' => now(),
                'current_period_ends_at' => $periodEnd ? now()->setTimestamp((int) $periodEnd) : $subscription->current_period_ends_at,
                'grace_ends_at' => null,
            ])->save();

            if ($subscription->status !== TenantSubscriptionStatus::Active && in_array('active', $this->lifecycle->allowedTransitions($subscription->status), true)) {
                $this->lifecycle->transition($subscription, TenantSubscriptionStatus::Active, 'Stripe payment received.');
            }
        }, 3);
    }

    private function paymentFailed(array $object): void
    {
        $providerSubscriptionId = (string) ($object['subscription'] ?? '');
        $subscription = TenantSubscription::query()
            ->with('plan')
            ->where('provider', 'stripe')
            ->where('provider_subscription_id', $providerSubscriptionId)
            ->first();

        if (! $subscription || $subscription->status !== TenantSubscriptionStatus::Active) {
            return;
        }

        $subscription->update(['grace_ends_at' => now()->addDays(max(1, (int) $subscription->plan->grace_days))]);
        $this->lifecycle->transition($subscription, TenantSubscriptionStatus::Grace, 'Stripe payment failed; grace period started.');
    }
}
