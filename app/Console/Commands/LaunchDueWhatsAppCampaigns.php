<?php

namespace App\Console\Commands;

use App\Contracts\TenantContext;
use App\Data\Campaigns\LaunchWhatsAppCampaignData;
use App\Models\WhatsAppCampaign;
use App\Services\Campaigns\LaunchWhatsAppCampaignService;
use Illuminate\Console\Command;
use Throwable;

final class LaunchDueWhatsAppCampaigns extends Command
{
    protected $signature = 'whatsapp-campaigns:launch-due {--limit=25}';

    protected $description = 'Create execution runs for due prepared campaigns without sending messages.';

    public function handle(TenantContext $context, LaunchWhatsAppCampaignService $service): int
    {
        $count = 0;
        WhatsAppCampaign::with('tenant')->where('status', 'prepared')->whereNotNull('scheduled_at_utc')->where('scheduled_at_utc', '<=', now())->orderBy('scheduled_at_utc')->limit((int) $this->option('limit'))->get()->each(function ($campaign) use ($context, $service, &$count) {
            try {
                $context->set($campaign->tenant);
                $service->launch($campaign, new LaunchWhatsAppCampaignData('scheduled:'.substr(hash('sha256', $campaign->uuid.'|'.$campaign->version.'|'.$campaign->payload_hash), 0, 64), $campaign->version, 'scheduled'));
                $count++;
            } catch (Throwable $e) {
                report($e);
            } finally {
                $context->clear();
            }
        });
        $this->info("Created {$count} execution run(s). No messages were sent.");

        return self::SUCCESS;
    }
}
