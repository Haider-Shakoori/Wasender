<?php

namespace App\Services\Campaigns;

use App\Enums\WhatsAppMessageTemplateStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignAttachment;
use App\Models\WhatsAppMessageTemplate;
use App\Services\AuditService;
use App\Services\Templates\WhatsAppMessageTemplateUsageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApplyWhatsAppMessageTemplateToCampaignService
{
    public function __construct(private CampaignPayloadHasher $hasher, private WhatsAppMessageTemplateUsageService $usages, private AuditService $audit) {}

    public function apply(WhatsAppCampaign $campaign, string $templateUuid, array $values, User $actor, int $expectedVersion): WhatsAppCampaign
    {
        return DB::transaction(function () use ($campaign, $templateUuid, $values, $actor, $expectedVersion): WhatsAppCampaign {
            $locked = WhatsAppCampaign::forTenant($campaign->tenant_id)->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if (! $locked->status->editable() || $locked->version !== $expectedVersion) {
                throw ValidationException::withMessages(['campaign' => 'The campaign is not editable or its version is stale.']);
            }
            $template = WhatsAppMessageTemplate::forTenant($campaign->tenant_id)->where('uuid', $templateUuid)->where('status', WhatsAppMessageTemplateStatus::Published)->with(['currentPublishedVersion.attachment'])->firstOrFail();
            $version = $template->currentPublishedVersion;
            if (! $version) {
                throw ValidationException::withMessages(['template_uuid' => 'The template has no published version.']);
            }
            $version->setRelation('template', $template);
            $type = WhatsAppMessageType::from($template->type->value);
            $locked->forceFill([
                'message_type' => $type, 'body' => $type === WhatsAppMessageType::Text ? $version->body : $version->caption,
                'message_template_id' => $template->id, 'message_template_version_id' => $version->id,
                'template_uuid' => $template->uuid, 'template_version_uuid' => $version->uuid, 'template_version_number' => $version->version_number,
                'template_content_hash' => $version->content_hash, 'template_variable_values' => $values,
                'template_content_customized' => false, 'template_rendered_at' => now(), 'updated_by' => $actor->id, 'version' => $locked->version + 1,
            ])->save();
            $locked->attachment()->delete();
            if ($media = $version->attachment) {
                WhatsAppCampaignAttachment::create(['tenant_id' => $locked->tenant_id, 'whatsapp_campaign_id' => $locked->id, 'disk' => $media->disk, 'storage_key' => $media->storage_key, 'original_name' => $media->original_name, 'safe_name' => $media->safe_name, 'mime_type' => $media->mime_type, 'extension' => $media->extension, 'size_bytes' => $media->size_bytes, 'checksum_sha256' => $media->checksum_sha256, 'media_category' => $media->media_category, 'width' => $media->width, 'height' => $media->height, 'duration_seconds' => $media->duration_seconds]);
            }
            $locked->load('attachment')->forceFill(['payload_hash' => $this->hasher->for($locked)])->save();
            $this->usages->record($version, 'campaign', $locked->uuid);
            $this->audit->recordDomain('whatsapp_campaign.template_applied', $actor, $locked->tenant, $locked, ['campaign_uuid' => $locked->uuid, 'template_uuid' => $template->uuid, 'template_version_uuid' => $version->uuid]);

            return $locked->fresh(['attachment', 'messageTemplateVersion']);
        }, 3);
    }

    public function detach(WhatsAppCampaign $campaign, User $actor, int $expectedVersion): WhatsAppCampaign
    {
        return DB::transaction(function () use ($campaign, $actor, $expectedVersion): WhatsAppCampaign {
            $locked = WhatsAppCampaign::forTenant($campaign->tenant_id)->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if (! $locked->status->editable() || $locked->version !== $expectedVersion) {
                throw ValidationException::withMessages(['campaign' => 'The campaign is not editable or its version is stale.']);
            }
            $locked->forceFill(['message_template_id' => null, 'message_template_version_id' => null, 'template_uuid' => null, 'template_version_uuid' => null, 'template_version_number' => null, 'template_content_hash' => null, 'template_variable_values' => null, 'template_content_customized' => true, 'template_rendered_at' => null, 'updated_by' => $actor->id, 'version' => $locked->version + 1])->save();
            $locked->load('attachment')->forceFill(['payload_hash' => $this->hasher->for($locked)])->save();
            $this->audit->recordDomain('whatsapp_campaign.template_detached', $actor, $locked->tenant, $locked, ['campaign_uuid' => $locked->uuid]);

            return $locked;
        }, 3);
    }
}
