<?php

namespace App\Console\Commands;

use App\Services\Campaigns\ReconcileWhatsAppCampaignTransportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class ReconcileWhatsAppCampaignTransport extends Command
{
    protected $signature = 'whatsapp-campaigns:reconcile-transport {--limit=}';

    protected $description = 'Reconcile uncertain WhatsApp campaign transport attempts without resending them';

    public function handle(ReconcileWhatsAppCampaignTransportService $service): int
    {
        $lock = Cache::lock('whatsapp-campaigns:reconcile-transport', 110);
        if (! $lock->get()) {
            return self::SUCCESS;
        }
        try {
            $stats = $service->reconcile((int) ($this->option('limit') ?: config('whatsapp_campaign_transport.reconcile_batch')));
            $this->components->info("Checked {$stats['checked']}; resolved {$stats['resolved']}; unresolved {$stats['unresolved']}.");
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
