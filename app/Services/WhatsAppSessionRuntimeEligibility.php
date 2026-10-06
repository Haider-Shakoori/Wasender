<?php

namespace App\Services;

use App\Enums\TenantSubscriptionStatus;
use App\Models\TenantSubscription;
use App\Models\WhatsAppSession;

final class WhatsAppSessionRuntimeEligibility
{
    public function eligible(WhatsAppSession $session): bool
    {
        $session->loadMissing(['tenant.currentSubscription']);

        $tenant = $session->tenant;
        if (! $tenant || ! $tenant->is_active) {
            return false;
        }

        /** @var TenantSubscription|null $subscription */
        $subscription = $tenant->currentSubscription;
        if (! $subscription || ! $subscription->status->permitsAccess()) {
            return false;
        }

        return match ($subscription->status) {
            TenantSubscriptionStatus::Trialing => ! $subscription->trial_ends_at || $subscription->trial_ends_at->isFuture(),
            TenantSubscriptionStatus::Active => ! $subscription->current_period_ends_at || $subscription->current_period_ends_at->isFuture(),
            TenantSubscriptionStatus::Grace => ! $subscription->grace_ends_at || $subscription->grace_ends_at->isFuture(),
            default => false,
        };
    }
}
