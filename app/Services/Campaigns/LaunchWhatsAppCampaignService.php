<?php

namespace App\Services\Campaigns;

use App\Contracts\TenantEntitlements;
use App\Data\Campaigns\LaunchWhatsAppCampaignData;
use App\Enums\WhatsAppCampaignEvent;
use App\Enums\WhatsAppCampaignExecutionStatus;
use App\Enums\WhatsAppCampaignPreparationStatus;
use App\Enums\WhatsAppCampaignRecipientExecutionStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Jobs\StartWhatsAppCampaignExecution;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignExecution;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LaunchWhatsAppCampaignService
{
    public function __construct(private TenantEntitlements $entitlements, private CampaignSessionSelector $sessions, private CampaignUsageReservationService $usage, private WhatsAppCampaignLifecycleService $lifecycle, private AuditService $audit) {}

    public function launch(WhatsAppCampaign $campaign, LaunchWhatsAppCampaignData $data, ?User $actor = null): WhatsAppCampaignExecution
    {
        $this->entitlements->requireFeature('campaigns.manage');
        $existing = WhatsAppCampaignExecution::where('tenant_id', $campaign->tenant_id)->where('idempotency_key', $data->idempotencyToken)->first();
        if ($existing) {
            if ($existing->campaign_version !== $data->expectedVersion || $existing->campaign_payload_hash !== $campaign->payload_hash) {
                throw ValidationException::withMessages(['idempotency_token' => 'This launch token was used for a different campaign payload.']);
            }

            $this->initializeRecipients($existing);
            if ($existing->status === WhatsAppCampaignExecutionStatus::Pending) {
                $existing->forceFill(['status' => WhatsAppCampaignExecutionStatus::Queued, 'queued_at' => $existing->queued_at ?? now()])->save();
                StartWhatsAppCampaignExecution::dispatch($existing->id);
            }

            return $existing->refresh();
        }
        $execution = DB::transaction(function () use ($campaign, $data, $actor) {
            $campaign = WhatsAppCampaign::forTenant($campaign->tenant_id)->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if ($campaign->status !== WhatsAppCampaignStatus::Prepared) {
                throw ValidationException::withMessages(['status' => 'Only a prepared campaign can launch.']);
            }
            if ($campaign->version !== $data->expectedVersion) {
                throw ValidationException::withMessages(['expected_version' => 'Campaign version changed.']);
            }
            $preparation = $campaign->activePreparation;
            if (! $preparation || $preparation->status !== WhatsAppCampaignPreparationStatus::Completed || $preparation->campaign_version !== $campaign->version || ! hash_equals($preparation->campaign_payload_hash, $campaign->payload_hash)) {
                throw ValidationException::withMessages(['preparation' => 'The active recipient preparation is missing or stale.']);
            }
            if ($campaign->eligible_recipient_count < 1) {
                throw ValidationException::withMessages(['recipients' => 'No eligible prepared recipients exist.']);
            }
            if (WhatsAppCampaignExecution::where('tenant_id', $campaign->tenant_id)->whereIn('status', ['pending', 'queued', 'running', 'pausing', 'paused', 'resuming', 'cancelling'])->count() >= config('whatsapp_campaign_execution.tenant_concurrency')) {
                throw ValidationException::withMessages(['execution' => 'Tenant campaign execution capacity is currently full.']);
            }
            if (WhatsAppCampaignExecution::where('whatsapp_campaign_id', $campaign->id)->whereIn('status', ['pending', 'queued', 'running', 'pausing', 'paused', 'resuming', 'cancelling'])->exists()) {
                throw ValidationException::withMessages(['execution' => 'A campaign execution is already active.']);
            }
            $probe = new WhatsAppCampaignExecution;
            $probe->forceFill(['tenant_id' => $campaign->tenant_id]);
            $probe->setRelation('campaign', $campaign);
            if (! $this->sessions->select($probe)) {
                throw ValidationException::withMessages(['sessions' => 'No eligible ready session is available.']);
            }
            $reservation = $this->usage->reserve($campaign->tenant, $campaign->eligible_recipient_count, 'campaign:'.$campaign->uuid.':v'.$campaign->version);
            $execution = new WhatsAppCampaignExecution;
            $execution->forceFill(['tenant_id' => $campaign->tenant_id, 'whatsapp_campaign_id' => $campaign->id, 'preparation_id' => $preparation->id, 'usage_reservation_id' => $reservation->id, 'status' => WhatsAppCampaignExecutionStatus::Pending, 'campaign_version' => $campaign->version, 'campaign_payload_hash' => $campaign->payload_hash, 'execution_config_hash' => hash('sha256', json_encode($campaign->execution_config ?? [], JSON_THROW_ON_ERROR)), 'idempotency_key' => $data->idempotencyToken, 'launch_type' => $data->launchType, 'scheduled_at' => $campaign->scheduled_at_utc, 'total_recipients' => $campaign->eligible_recipient_count, 'pending_recipients' => $campaign->eligible_recipient_count, 'created_by' => $actor?->id, 'launched_by' => $actor?->id])->save();
            $campaign->forceFill(['active_execution_id' => $execution->id, 'launch_requested_at' => now(), 'launched_by' => $actor?->id])->save();
            $this->lifecycle->transition($campaign, WhatsAppCampaignStatus::Queued, WhatsAppCampaignEvent::Queued, $actor, $campaign->version, ['execution_uuid' => $execution->uuid]);

            return $execution;
        });
        $this->initializeRecipients($execution);
        $execution->forceFill(['status' => WhatsAppCampaignExecutionStatus::Queued, 'queued_at' => now()])->save();
        $this->audit->recordDomain('whatsapp_campaign.launch_requested', $actor, $campaign->tenant, $campaign, ['campaign_uuid' => $campaign->uuid, 'execution_uuid' => $execution->uuid, 'preparation_uuid' => $execution->preparation->uuid, 'total_recipients' => $execution->total_recipients]);
        StartWhatsAppCampaignExecution::dispatch($execution->id);

        return $execution->refresh();
    }

    private function initializeRecipients(WhatsAppCampaignExecution $execution): void
    {
        $execution->preparation->recipients()->orderBy('id')->chunkById(config('whatsapp_campaign_execution.claim_size'), function ($recipients) use ($execution) {
            $now = now();
            $rows = $recipients->map(fn ($r) => ['uuid' => (string) Str::uuid(), 'tenant_id' => $execution->tenant_id, 'whatsapp_campaign_id' => $execution->whatsapp_campaign_id, 'campaign_execution_id' => $execution->id, 'campaign_recipient_id' => $r->id, 'status' => WhatsAppCampaignRecipientExecutionStatus::Pending->value, 'attempt_count' => 0, 'max_attempts' => min(config('whatsapp_campaign_execution.max_attempts'), (int) data_get($execution->campaign->execution_config, 'max_attempts_per_recipient', 2)), 'idempotency_key' => $execution->uuid.':'.$r->uuid, 'priority' => 0, 'lock_version' => 1, 'created_at' => $now, 'updated_at' => $now])->all();
            DB::table('whatsapp_campaign_recipient_executions')->insertOrIgnore($rows);
        });
    }
}
