<?php

namespace App\Services;

use App\Enums\TenantSubscriptionStatus as S;
use App\Exceptions\SubscriptionTransitionException;
use App\Models\SubscriptionStatusHistory;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SubscriptionLifecycleService
{
    private const ALLOWED = ['trialing' => ['active', 'expired', 'cancelled'], 'active' => ['grace', 'suspended', 'cancelled', 'expired'], 'grace' => ['active', 'suspended', 'expired'], 'suspended' => ['active'], 'cancelled' => ['active', 'expired'], 'expired' => ['active']];

    public function __construct(private PlatformAuditService $audit) {}

    /** @return list<string> */
    public function allowedTransitions(S $from): array
    {
        return self::ALLOWED[$from->value] ?? [];
    }

    public function transition(TenantSubscription $subscription, S $to, string $reason, ?User $actor = null): TenantSubscription
    {
        return DB::transaction(function () use ($subscription, $to, $reason, $actor) {
            $locked = TenantSubscription::lockForUpdate()->findOrFail($subscription->id);
            $from = $locked->status;
            if ($from === $to || ! in_array($to->value, self::ALLOWED[$from->value] ?? [], true)) {
                throw new SubscriptionTransitionException("Transition from {$from->value} to {$to->value} is not allowed.");
            }$changes = ['status' => $to];
            if (in_array($to, [S::Expired, S::Cancelled], true)) {
                $changes[$to === S::Cancelled ? 'cancelled_at' : 'ended_at'] = now();
            }$locked->update($changes);
            SubscriptionStatusHistory::create(['subscription_id' => $locked->id, 'from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'actor_type' => $actor ? 'platform' : 'system', 'actor_id' => $actor?->id, 'effective_at' => now(), 'created_at' => now()]);
            $this->audit->record('subscription.status_changed', $actor, $locked->tenant, ['from' => $from->value, 'to' => $to->value, 'reason' => $reason]);

            return $locked->refresh();
        }, 3);
    }

    public function extendTrial(TenantSubscription $s, \DateTimeInterface $until, string $reason, User $actor): TenantSubscription
    {
        if ($s->status !== S::Trialing || $until <= ($s->trial_ends_at ?? now())) {
            throw new SubscriptionTransitionException('Trial extension must move the current trial end forward.');
        }$s->update(['trial_ends_at' => $until]);
        SubscriptionStatusHistory::create(['subscription_id' => $s->id, 'from_status' => S::Trialing, 'to_status' => S::Trialing, 'reason' => 'Trial extended: '.$reason, 'actor_type' => 'platform', 'actor_id' => $actor->id, 'effective_at' => now(), 'created_at' => now()]);
        $this->audit->record('subscription.trial_extended', $actor, $s->tenant, ['trial_ends_at' => $s->trial_ends_at->toIso8601String(), 'reason' => $reason]);

        return $s->refresh();
    }

    public function extendPeriod(TenantSubscription $subscription, \DateTimeInterface $until, string $reason, User $actor): TenantSubscription
    {
        return DB::transaction(function () use ($subscription, $until, $reason, $actor): TenantSubscription {
            $locked = TenantSubscription::lockForUpdate()->findOrFail($subscription->id);
            if ($until <= ($locked->current_period_ends_at ?? now())) {
                throw new SubscriptionTransitionException('The new period end must be later than the current period end.');
            }
            $locked->update(['current_period_ends_at' => $until]);
            SubscriptionStatusHistory::create(['subscription_id' => $locked->id, 'from_status' => $locked->status, 'to_status' => $locked->status, 'reason' => 'Period extended: '.$reason, 'actor_type' => 'platform', 'actor_id' => $actor->id, 'effective_at' => now(), 'created_at' => now()]);
            $this->audit->record('subscription.period.extended', $actor, $locked->tenant, ['period_ends_at' => $locked->current_period_ends_at->toIso8601String(), 'reason' => $reason]);

            return $locked->refresh();
        }, 3);
    }
}
