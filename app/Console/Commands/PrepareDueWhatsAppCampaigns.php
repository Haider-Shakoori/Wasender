<?php

namespace App\Console\Commands;

use App\Contracts\TenantContext;
use App\Data\Campaigns\PrepareWhatsAppCampaignData;
use App\Models\WhatsAppCampaign;
use App\Services\Campaigns\PrepareWhatsAppCampaignService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

final class PrepareDueWhatsAppCampaigns extends Command
{
    protected $signature = 'whatsapp-campaigns:prepare-due {--limit=50}';

    protected $description = 'Queue recipient preparation for scheduled WhatsApp campaigns; never sends messages.';

    public function handle(TenantContext $context, PrepareWhatsAppCampaignService $service): int
    {
        $count = 0;
        WhatsAppCampaign::with('tenant')->where('status', 'scheduled')->where('scheduled_at_utc', '<=', now()->addMinutes(config('whatsapp_campaigns.preparation_lead_minutes')))->orderBy('scheduled_at_utc')->limit((int) $this->option('limit'))->get()->each(function (WhatsAppCampaign $campaign) use ($context, $service, &$count): void {
            try {
                $context->set($campaign->tenant);
                $key = 'scheduled:'.hash('sha256', $campaign->uuid.'|'.$campaign->version.'|'.$campaign->payload_hash);
                $service->prepare($campaign, new PrepareWhatsAppCampaignData(Str::limit($key, 80, ''), $campaign->version));
                $count++;
            } catch (Throwable $e) {
                report($e);
            } finally {
                $context->clear();
            }
        });
        $this->info("Queued {$count} campaign preparation(s). No messages were sent.");

        return self::SUCCESS;
    }
}
