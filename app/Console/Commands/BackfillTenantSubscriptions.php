<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionSource;
use App\Models\Tenant;
use App\Services\SubscriptionBootstrapService;
use Illuminate\Console\Command;
use Throwable;

final class BackfillTenantSubscriptions extends Command
{
    protected $signature = 'subscriptions:backfill {--dry-run} {--plan=} {--trial-days=}';

    protected $description = 'Assign the configured plan to tenants without a current subscription';

    public function handle(SubscriptionBootstrapService $service): int
    {
        $count = 0;
        try {
            Tenant::query()->whereDoesntHave('subscriptions', fn ($q) => $q->where('is_current', true))->chunkById(100, function ($tenants) use ($service, &$count) {
                foreach ($tenants as $tenant) {
                    $count++;
                    if (! $this->option('dry-run')) {
                        $service->assignDefault($tenant, null, $this->option('plan') ?: null, $this->option('trial-days') !== null ? (int) $this->option('trial-days') : null, SubscriptionSource::Migration);
                    }
                }
            });
            $this->info(($this->option('dry-run') ? 'Would assign' : 'Assigned')." {$count} tenant subscription(s).");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
