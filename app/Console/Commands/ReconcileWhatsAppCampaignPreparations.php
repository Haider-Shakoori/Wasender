<?php

namespace App\Console\Commands;

use App\Models\WhatsAppCampaignPreparation;
use App\Services\Campaigns\ReconcileWhatsAppCampaignPreparationService;
use Illuminate\Console\Command;

final class ReconcileWhatsAppCampaignPreparations extends Command
{
    protected $signature = 'whatsapp-campaigns:reconcile-preparations {--limit=100}';

    protected $description = 'Reconcile campaign recipient preparations without launching campaigns.';

    public function handle(ReconcileWhatsAppCampaignPreparationService $service): int
    {
        WhatsAppCampaignPreparation::with('campaign')->whereIn('status', ['pending', 'running', 'finalizing', 'completed'])->oldest('updated_at')->limit((int) $this->option('limit'))->get()->each(fn ($preparation) => $service->reconcile($preparation));

        return self::SUCCESS;
    }
}
