<?php

namespace App\Console\Commands;

use App\Models\IntegrationWebhookDelivery;
use Illuminate\Console\Command;

final class ReconcileIntegrationWebhooks extends Command
{
    protected $signature = 'integrations:reconcile-webhooks {--limit=100}';

    protected $description = 'Mark bounded stale webhook deliveries for safe operator review without resending';

    public function handle(): int
    {
        $limit = min(500, max(1, (int) $this->option('limit')));
        $count = 0;
        IntegrationWebhookDelivery::query()->whereIn('status', ['sending', 'retrying'])
            ->where('updated_at', '<', now()->subMinutes(config('operations.stale_minutes')))
            ->orderBy('id')->limit($limit)->get(['id'])->each(function (IntegrationWebhookDelivery $delivery) use (&$count): void {
                $count += IntegrationWebhookDelivery::whereKey($delivery->id)->whereIn('status', ['sending', 'retrying'])->update([
                    'status' => 'failed',
                    'failure_code' => 'reconciliation_required',
                    'next_retry_at' => null,
                    'completed_at' => now(),
                ]);
            });
        $this->info("Reconciled {$count} stale webhook deliveries.");

        return self::SUCCESS;
    }
}
