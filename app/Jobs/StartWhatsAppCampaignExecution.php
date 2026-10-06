<?php

namespace App\Jobs;

use App\Contracts\TenantContext;
use App\Enums\WhatsAppCampaignEvent;
use App\Enums\WhatsAppCampaignExecutionStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\WhatsAppCampaignExecution;
use App\Services\Campaigns\WhatsAppCampaignLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class StartWhatsAppCampaignExecution implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $executionId)
    {
        $this->onQueue(config('whatsapp_campaign_execution.queues.control'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("wa-execution-start:{$this->executionId}"))->expireAfter(120)];
    }

    public function handle(TenantContext $context, WhatsAppCampaignLifecycleService $lifecycle): void
    {
        $execution = WhatsAppCampaignExecution::with(['tenant', 'campaign'])->findOrFail($this->executionId);
        $context->set($execution->tenant);
        if ($execution->status !== WhatsAppCampaignExecutionStatus::Queued) {
            return;
        }$execution->forceFill(['status' => WhatsAppCampaignExecutionStatus::Running, 'started_at' => now(), 'last_heartbeat_at' => now()])->save();
        if ($execution->campaign->status === WhatsAppCampaignStatus::Queued) {
            $lifecycle->transition($execution->campaign, WhatsAppCampaignStatus::Running, WhatsAppCampaignEvent::Started, null);
        }ContinueWhatsAppCampaignExecution::dispatch($execution->id);
    }
}
