<?php

namespace App\Services\Campaigns;

use App\Contracts\TenantEntitlements;
use App\Data\Campaigns\PrepareWhatsAppCampaignData;
use App\Enums\WhatsAppCampaignEvent;
use App\Enums\WhatsAppCampaignPreparationStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Jobs\ProcessWhatsAppCampaignPreparationChunk;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignPreparation;
use App\Services\AuditService;
use App\Services\Campaigns\Audience\CampaignAudienceResolverRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PrepareWhatsAppCampaignService
{
    public function __construct(private TenantEntitlements $entitlements, private WhatsAppCampaignValidationService $validation, private CampaignAudienceDefinitionHasher $audienceHasher, private CampaignPayloadHasher $payloadHasher, private CampaignAudienceResolverRegistry $resolvers, private WhatsAppCampaignLifecycleService $lifecycle, private AuditService $audit) {}

    public function prepare(WhatsAppCampaign $campaign, PrepareWhatsAppCampaignData $data, ?User $actor = null): WhatsAppCampaignPreparation
    {
        $this->entitlements->requireFeature('campaigns.manage');
        $existing = WhatsAppCampaignPreparation::where('tenant_id', $campaign->tenant_id)->where('idempotency_key', $data->idempotencyToken)->first();
        $definitionHash = $this->audienceHasher->hash($campaign);
        if ($existing) {
            if ($existing->campaign_version !== $data->expectedVersion || $existing->campaign_payload_hash !== $campaign->payload_hash || $existing->audience_definition_hash !== $definitionHash) {
                throw ValidationException::withMessages(['idempotency_token' => 'This token was already used for a different preparation payload.']);
            }

            return $existing;
        }

        $preparation = DB::transaction(function () use ($campaign, $data, $actor, $definitionHash) {
            $locked = WhatsAppCampaign::forTenant($campaign->tenant_id)->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $sameRequest = WhatsAppCampaignPreparation::where('tenant_id', $locked->tenant_id)->where('idempotency_key', $data->idempotencyToken)->first();
            if ($sameRequest) {
                return $sameRequest;
            }
            if (! in_array($locked->status, [WhatsAppCampaignStatus::Ready, WhatsAppCampaignStatus::Scheduled], true)) {
                throw ValidationException::withMessages(['status' => 'Campaign must be ready or scheduled before preparation.']);
            }
            if ($locked->version !== $data->expectedVersion) {
                throw ValidationException::withMessages(['expected_version' => 'Campaign version changed. Refresh and try again.']);
            }
            if (! hash_equals($locked->payload_hash, $this->payloadHasher->for($locked->load('attachment')))) {
                throw ValidationException::withMessages(['campaign' => 'Campaign payload hash is stale. Save and validate the campaign again.']);
            }
            $result = $this->validation->validate($locked->load(['attachment', 'sessionSelections.session']));
            if (! $result->valid()) {
                throw ValidationException::withMessages(['campaign' => $result->errors]);
            }
            if (WhatsAppCampaignPreparation::where('whatsapp_campaign_id', $locked->id)->whereIn('status', ['pending', 'running', 'finalizing'])->exists()) {
                throw ValidationException::withMessages(['campaign' => 'A preparation is already active.']);
            }

            $estimate = $this->resolvers->for($locked->audience_type)->estimate($locked->tenant, $locked);
            WhatsAppCampaignPreparation::where('whatsapp_campaign_id', $locked->id)->where('status', 'completed')->update(['status' => WhatsAppCampaignPreparationStatus::Stale]);
            $locked->recipients()->delete();
            $locked->exclusions()->delete();
            $preparation = new WhatsAppCampaignPreparation;
            $preparation->forceFill(['tenant_id' => $locked->tenant_id, 'whatsapp_campaign_id' => $locked->id, 'status' => WhatsAppCampaignPreparationStatus::Pending, 'campaign_version' => $locked->version, 'campaign_payload_hash' => $locked->payload_hash, 'audience_type' => $locked->audience_type, 'audience_definition_hash' => $definitionHash, 'idempotency_key' => $data->idempotencyToken, 'total_candidates' => $estimate->candidateCount, 'created_by' => $actor?->id])->save();
            $this->lifecycle->transition($locked, WhatsAppCampaignStatus::Preparing, WhatsAppCampaignEvent::PreparationRequested, $actor, $data->expectedVersion, ['preparation_uuid' => $preparation->uuid]);

            return $preparation;
        });
        ProcessWhatsAppCampaignPreparationChunk::dispatch($preparation->id);
        $this->audit->recordDomain('whatsapp_campaign.preparation_requested', $actor, $campaign->tenant, $campaign, ['campaign_uuid' => $campaign->uuid, 'preparation_uuid' => $preparation->uuid, 'campaign_version' => $campaign->version]);

        return $preparation;
    }
}
