<?php

namespace App\Jobs;

use App\Enums\WhatsAppCampaignExecutionStatus;
use App\Models\WhatsAppCampaignExecution;
use App\Services\Campaigns\ClaimCampaignRecipientsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class ContinueWhatsAppCampaignExecution implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 30;

    public function __construct(public int $executionId)
    {
        $this->onQueue(config('whatsapp_campaign_execution.queues.control'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("wa-execution-continue:{$this->executionId}"))->expireAfter(120)];
    }

    public function uniqueId(): string
    {
        return (string) $this->executionId;
    }

    public function handle(ClaimCampaignRecipientsService $claims): void
    {
        $execution = WhatsAppCampaignExecution::findOrFail($this->executionId);
        if ($execution->status !== WhatsAppCampaignExecutionStatus::Running) {
            return;
        }$claims->claim($execution);
    }
}
