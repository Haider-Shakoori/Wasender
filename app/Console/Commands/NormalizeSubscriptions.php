<?php

namespace App\Console\Commands;

use App\Enums\TenantSubscriptionStatus as S;
use App\Models\TenantSubscription;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Console\Command;

final class NormalizeSubscriptions extends Command
{
    protected $signature = 'subscriptions:normalize';

    protected $description = 'Normalize expired subscription time windows';

    public function handle(SubscriptionLifecycleService $service): int
    {
        $count = 0;
        TenantSubscription::current()->where(function ($q) {
            $q->where(fn ($q) => $q->where('status', S::Trialing)->where('trial_ends_at', '<=', now()))->orWhere(fn ($q) => $q->where('status', S::Grace)->where('grace_ends_at', '<=', now()))->orWhere(fn ($q) => $q->where('status', S::Cancelled)->where('current_period_ends_at', '<=', now()));
        })->chunkById(100, function ($subs) use ($service, &$count) {
            foreach ($subs as $s) {
                $service->transition($s, S::Expired, 'Scheduled access window ended');
                $count++;
            }
        });
        $this->info("Normalized {$count} subscription(s).");

        return self::SUCCESS;
    }
}
