<?php

namespace App\Services\Campaigns;

use App\Enums\WhatsAppCampaignEvent;
use App\Enums\WhatsAppCampaignExecutionStatus;
use App\Enums\WhatsAppCampaignRecipientExecutionStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\WhatsAppCampaignExecution;

final class ReconcileWhatsAppCampaignExecutionService
{
    public function __construct(private WhatsAppCampaignLifecycleService $lifecycle, private CampaignUsageReservationService $usage) {}

    public function reconcile(WhatsAppCampaignExecution $execution): array
    {
        $heartbeatWasStale = $execution->status === WhatsAppCampaignExecutionStatus::Running
            && (! $execution->last_heartbeat_at || $execution->last_heartbeat_at->lt(now()->subMinutes(config('whatsapp_campaign_execution.stale_minutes'))));
        $stale = $execution->recipients()->whereIn('status', ['claimed', 'processing'])->where('claimed_at', '<', now()->subMinutes(config('whatsapp_campaign_execution.claim_timeout_minutes')))->update(['status' => WhatsAppCampaignRecipientExecutionStatus::RetryScheduled->value, 'next_attempt_at' => now(), 'claimed_at' => null, 'processing_started_at' => null, 'failure_code' => 'queue_interruption']);
        $counts = $execution->recipients()->selectRaw('status,count(*) as total')->groupBy('status')->pluck('total', 'status');
        $sent = ($counts['sent'] ?? 0) + ($counts['delivered'] ?? 0) + ($counts['read'] ?? 0);
        $delivered = ($counts['delivered'] ?? 0) + ($counts['read'] ?? 0);
        $read = $counts['read'] ?? 0;
        $terminal = $sent + ($counts['failed'] ?? 0) + ($counts['skipped'] ?? 0) + ($counts['cancelled'] ?? 0);
        $total = $execution->total_recipients;
        $execution->forceFill(['pending_recipients' => $counts['pending'] ?? 0, 'queued_recipients' => $counts['queued'] ?? 0, 'processing_recipients' => ($counts['claimed'] ?? 0) + ($counts['processing'] ?? 0), 'transport_pending_recipients' => $counts['transport_pending'] ?? 0, 'sent_recipients' => $sent, 'delivered_recipients' => $delivered, 'read_recipients' => $read, 'failed_recipients' => $counts['failed'] ?? 0, 'skipped_recipients' => $counts['skipped'] ?? 0, 'cancelled_recipients' => $counts['cancelled'] ?? 0, 'retry_scheduled_recipients' => $counts['retry_scheduled'] ?? 0, 'unknown_recipients' => $execution->attempts()->where('status', 'unknown')->count(), 'progress_percentage' => $total ? min(100, round($terminal / $total * 100, 2)) : 0, 'last_heartbeat_at' => now()])->save();
        $execution->campaign->forceFill(['queued_recipient_count' => $execution->pending_recipients + $execution->queued_recipients, 'processing_recipient_count' => $execution->processing_recipients, 'sent_recipient_count' => $sent, 'delivered_recipient_count' => $delivered, 'read_recipient_count' => $read, 'failed_recipient_count' => $execution->failed_recipients, 'skipped_recipient_count' => $execution->skipped_recipients, 'cancelled_recipient_count' => $execution->cancelled_recipients, 'progress_percentage' => $execution->progress_percentage, 'last_progress_at' => now()])->save();
        if ($heartbeatWasStale) {
            $execution->forceFill(['failure_code' => 'execution_stuck'])->save();
        }
        if ($execution->status === WhatsAppCampaignExecutionStatus::Pausing && $execution->processing_recipients === 0) {
            $execution->forceFill(['status' => WhatsAppCampaignExecutionStatus::Paused, 'paused_at' => now()])->save();
            if ($execution->campaign->status === WhatsAppCampaignStatus::Pausing) {
                $this->lifecycle->transition($execution->campaign, WhatsAppCampaignStatus::Paused, WhatsAppCampaignEvent::Paused);
            }
        }
        if ($execution->status === WhatsAppCampaignExecutionStatus::Cancelling
            && $execution->processing_recipients === 0
            && $execution->transport_pending_recipients === 0
            && $execution->unknown_recipients === 0) {
            $cancelled = $execution->recipients()->whereIn('status', ['pending', 'queued', 'claimed', 'retry_scheduled'])->update(['status' => WhatsAppCampaignRecipientExecutionStatus::Cancelled->value, 'cancelled_at' => now(), 'failure_code' => 'cancelled_before_send']);
            $execution->forceFill(['status' => WhatsAppCampaignExecutionStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_recipients' => $execution->cancelled_recipients + $cancelled])->save();
            if ($execution->reservation) {
                $this->usage->release($execution->reservation, $execution->reservation->available());
            }
            if ($execution->campaign->status === WhatsAppCampaignStatus::Cancelling) {
                $this->lifecycle->transition($execution->campaign, WhatsAppCampaignStatus::Cancelled, WhatsAppCampaignEvent::Cancelled);
            }
        }

        return ['stale_claims' => $stale, 'transport_pending' => $execution->transport_pending_recipients, 'terminal' => $terminal];
    }
}
