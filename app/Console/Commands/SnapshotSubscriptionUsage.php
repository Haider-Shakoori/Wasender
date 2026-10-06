<?php

namespace App\Console\Commands;

use App\Models\SubscriptionUsageSnapshot;
use App\Models\Tenant;
use App\Services\SubscriptionUsageRegistry;
use Illuminate\Console\Command;

final class SnapshotSubscriptionUsage extends Command
{
    protected $signature = 'subscriptions:snapshot-usage';

    protected $description = 'Capture current measurable subscription usage';

    public function handle(SubscriptionUsageRegistry $usage): int
    {
        $window = now()->startOfDay();
        $count = 0;
        Tenant::with('currentSubscription')->whereHas('currentSubscription')->chunkById(100, function ($tenants) use ($usage, $window, &$count) {
            foreach ($tenants as $tenant) {
                foreach ($usage->measurableKeys() as $key) {
                    SubscriptionUsageSnapshot::updateOrCreate(['tenant_id' => $tenant->id, 'metric_key' => $key, 'period_starts_at' => $window], ['subscription_id' => $tenant->currentSubscription->id, 'value' => $usage->usage($tenant, $key), 'captured_at' => now()]);
                    $count++;
                }
            }
        });
        $this->info("Captured {$count} usage metric(s).");

        return self::SUCCESS;
    }
}
