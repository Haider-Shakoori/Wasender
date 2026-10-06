<?php

namespace App\Services\Campaigns;

use App\Contracts\TenantEntitlements;
use App\Data\Campaigns\CampaignControlData;
use App\Enums\WhatsAppCampaignEvent;
use App\Enums\WhatsAppCampaignExecutionStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Jobs\ContinueWhatsAppCampaignExecution;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResumeWhatsAppCampaignService
{
    public function __construct(private TenantEntitlements $entitlements, private CampaignSessionSelector $sessions, private WhatsAppCampaignLifecycleService $lifecycle, private AuditService $audit) {}

    public function resume(WhatsAppCampaign $campaign, CampaignControlData $data, User $actor): void
    {
        $this->entitlements->requireFeature('campaigns.manage');
        $execution = DB::transaction(function () use ($campaign, $data, $actor) {
            $campaign = WhatsAppCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $execution = $campaign->activeExecution;
            if (! $execution || $execution->status !== WhatsAppCampaignExecutionStatus::Paused) {
                return null;
            }if ($campaign->version !== $data->expectedVersion || $execution->campaign_version !== $campaign->version || ! hash_equals($execution->campaign_payload_hash, $campaign->payload_hash)) {
                throw ValidationException::withMessages(['campaign' => 'Campaign or preparation is stale.']);
            }if (! $this->sessions->select($execution)) {
                throw ValidationException::withMessages(['sessions' => 'No ready session capacity is available.']);
            }$execution->forceFill(['status' => WhatsAppCampaignExecutionStatus::Resuming, 'resume_requested_at' => now(), 'resumed_by' => $actor->id])->save();
            $this->lifecycle->transition($campaign, WhatsAppCampaignStatus::Resuming, WhatsAppCampaignEvent::ResumeRequested, $actor, $data->expectedVersion);
            $execution->forceFill(['status' => WhatsAppCampaignExecutionStatus::Running, 'resumed_at' => now(), 'pause_requested_at' => null, 'last_heartbeat_at' => now()])->save();
            $this->lifecycle->transition($campaign->refresh(), WhatsAppCampaignStatus::Running, WhatsAppCampaignEvent::Resumed, $actor);

            return $execution;
        });
        if ($execution) {
            ContinueWhatsAppCampaignExecution::dispatch($execution->id);
            $this->audit->recordDomain('whatsapp_campaign.resumed', $actor, $campaign->tenant, $campaign, ['campaign_uuid' => $campaign->uuid, 'execution_uuid' => $execution->uuid]);
        }
    }
}
