<?php

namespace App\Services\Campaigns;

use App\Data\Campaigns\CampaignControlData;
use App\Enums\WhatsAppCampaignEvent;
use App\Enums\WhatsAppCampaignExecutionStatus;
use App\Enums\WhatsAppCampaignRecipientExecutionStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelWhatsAppCampaignExecutionService
{
    public function __construct(private CampaignUsageReservationService $usage, private WhatsAppCampaignLifecycleService $lifecycle, private AuditService $audit) {}

    public function cancel(WhatsAppCampaign $campaign, CampaignControlData $data, User $actor): void
    {
        DB::transaction(function () use ($campaign, $data, $actor) {
            $campaign = WhatsAppCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $execution = $campaign->activeExecution;
            if (! $execution || in_array($execution->status, [WhatsAppCampaignExecutionStatus::Cancelled, WhatsAppCampaignExecutionStatus::Completed, WhatsAppCampaignExecutionStatus::CompletedWithErrors], true)) {
                return;
            }if ($campaign->version !== $data->expectedVersion) {
                throw ValidationException::withMessages(['expected_version' => 'Campaign version changed.']);
            }$execution->forceFill(['status' => WhatsAppCampaignExecutionStatus::Cancelling, 'cancel_requested_at' => now(), 'cancelled_by' => $actor->id])->save();
            if ($campaign->status !== WhatsAppCampaignStatus::Cancelling) {
                $this->lifecycle->transition($campaign, WhatsAppCampaignStatus::Cancelling, WhatsAppCampaignEvent::CancelRequested, $actor, $data->expectedVersion);
            }$cancelled = $execution->recipients()->whereIn('status', ['pending', 'queued', 'claimed', 'retry_scheduled'])->update(['status' => WhatsAppCampaignRecipientExecutionStatus::Cancelled->value, 'cancelled_at' => now(), 'failure_code' => 'cancelled_before_send', 'failure_message' => 'Cancelled before reaching transport.']);
            $inflight = $execution->recipients()
                ->whereIn('status', ['processing', 'transport_pending'])
                ->exists()
                || $execution->attempts()->where('status', 'unknown')->exists();
            if (! $inflight) {
                $execution->forceFill(['status' => WhatsAppCampaignExecutionStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_recipients' => $cancelled, 'pending_recipients' => 0, 'queued_recipients' => 0, 'retry_scheduled_recipients' => 0])->save();
                $this->usage->release($execution->reservation, $execution->reservation->available());
                $this->lifecycle->transition($campaign->refresh(), WhatsAppCampaignStatus::Cancelled, WhatsAppCampaignEvent::Cancelled, $actor);
                $campaign->forceFill(['cancelled_recipient_count' => $cancelled])->save();
            }$this->audit->recordDomain('whatsapp_campaign.cancel_requested', $actor, $campaign->tenant, $campaign, ['campaign_uuid' => $campaign->uuid, 'execution_uuid' => $execution->uuid, 'cancelled_count' => $cancelled]);
        });
    }
}
