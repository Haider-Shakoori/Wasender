<?php

namespace App\Services\Campaigns;

use App\Data\Campaigns\CampaignControlData;
use App\Enums\WhatsAppCampaignEvent;
use App\Enums\WhatsAppCampaignExecutionStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PauseWhatsAppCampaignService
{
    public function __construct(private WhatsAppCampaignLifecycleService $lifecycle, private AuditService $audit) {}

    public function pause(WhatsAppCampaign $campaign, CampaignControlData $data, User $actor): void
    {
        DB::transaction(function () use ($campaign, $data, $actor) {
            $campaign = WhatsAppCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $execution = $campaign->activeExecution;
            if (! $execution || ! in_array($execution->status, [WhatsAppCampaignExecutionStatus::Queued, WhatsAppCampaignExecutionStatus::Running, WhatsAppCampaignExecutionStatus::Pausing], true)) {
                return;
            }if ($campaign->version !== $data->expectedVersion) {
                throw ValidationException::withMessages(['expected_version' => 'Campaign version changed.']);
            }if ($execution->status === WhatsAppCampaignExecutionStatus::Running) {
                $execution->forceFill(['status' => WhatsAppCampaignExecutionStatus::Pausing, 'pause_requested_at' => now(), 'paused_by' => $actor->id])->save();
                $this->lifecycle->transition($campaign, WhatsAppCampaignStatus::Pausing, WhatsAppCampaignEvent::PauseRequested, $actor, $data->expectedVersion);
            }if (! $execution->recipients()->whereIn('status', ['claimed', 'processing'])->exists()) {
                $execution->forceFill(['status' => WhatsAppCampaignExecutionStatus::Paused, 'paused_at' => now(), 'last_heartbeat_at' => now()])->save();
                $this->lifecycle->transition($campaign->refresh(), WhatsAppCampaignStatus::Paused, WhatsAppCampaignEvent::Paused, $actor);
            }$this->audit->recordDomain('whatsapp_campaign.pause_requested', $actor, $campaign->tenant, $campaign, ['campaign_uuid' => $campaign->uuid, 'execution_uuid' => $execution->uuid]);
        });
    }
}
