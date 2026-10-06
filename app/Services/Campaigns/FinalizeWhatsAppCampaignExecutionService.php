<?php

namespace App\Services\Campaigns;

use App\Enums\WhatsAppCampaignEvent;
use App\Enums\WhatsAppCampaignExecutionStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\WhatsAppCampaignExecution;

final class FinalizeWhatsAppCampaignExecutionService
{
    public function __construct(private WhatsAppCampaignLifecycleService $lifecycle) {}

    public function finalize(WhatsAppCampaignExecution $execution): bool
    {
        $counts = $execution->recipients()->selectRaw('status,count(*) as total')->groupBy('status')->pluck('total', 'status');
        foreach (['pending', 'queued', 'claimed', 'processing', 'retry_scheduled', 'transport_pending'] as $open) {
            if (($counts[$open] ?? 0) > 0) {
                return false;
            }
        }
        if ($execution->attempts()->where('status', 'unknown')->exists()) {
            return false;
        }
        if (in_array($execution->status, [WhatsAppCampaignExecutionStatus::Paused, WhatsAppCampaignExecutionStatus::Cancelling, WhatsAppCampaignExecutionStatus::Cancelled], true)) {
            return false;
        }
        $failed = ($counts['failed'] ?? 0) + ($counts['skipped'] ?? 0);
        $status = $failed ? WhatsAppCampaignExecutionStatus::CompletedWithErrors : WhatsAppCampaignExecutionStatus::Completed;
        $campaignStatus = $failed ? WhatsAppCampaignStatus::CompletedWithErrors : WhatsAppCampaignStatus::Completed;
        $event = $failed ? WhatsAppCampaignEvent::ExecutionCompletedWithErrors : WhatsAppCampaignEvent::ExecutionCompleted;
        $execution->forceFill(['status' => $status, 'completed_at' => now(), 'progress_percentage' => 100])->save();
        $this->lifecycle->transition($execution->campaign, $campaignStatus, $event);

        return true;
    }
}
