<?php

namespace App\Console\Commands;

use App\Models\WhatsAppCampaignExecution;
use App\Services\Campaigns\ReconcileWhatsAppCampaignExecutionService;
use Illuminate\Console\Command;

final class ReconcileWhatsAppCampaignExecutions extends Command
{
    protected $signature = 'whatsapp-campaigns:reconcile-executions {--limit=100}';

    protected $description = 'Reconcile execution counters and stale claims without transport.';

    public function handle(ReconcileWhatsAppCampaignExecutionService $service): int
    {
        WhatsAppCampaignExecution::whereIn('status', ['queued', 'running', 'pausing', 'paused', 'resuming', 'cancelling'])->oldest('last_heartbeat_at')->limit((int) $this->option('limit'))->get()->each(fn ($execution) => $service->reconcile($execution));

        return self::SUCCESS;
    }
}
