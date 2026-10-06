<?php

namespace App\Services\Templates;

use App\Contracts\TenantContext;
use App\Data\Templates\CreateWhatsAppMessageTemplateData;
use App\Data\Templates\DuplicateWhatsAppMessageTemplateData;
use App\Models\User;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppMessageTemplateAttachment;
use Illuminate\Validation\ValidationException;

final class DuplicateWhatsAppMessageTemplateService
{
    public function __construct(private TenantContext $context, private CreateWhatsAppMessageTemplateService $creator, private TemplateAudit $audit) {}

    public function duplicate(WhatsAppMessageTemplate $source, DuplicateWhatsAppMessageTemplateData $data, User $actor): WhatsAppMessageTemplate
    {
        $source = WhatsAppMessageTemplate::forTenant($this->context->id())->whereKey($source->id)->firstOrFail();
        $version = $data->preferDraft ? $source->currentDraftVersion : ($source->currentPublishedVersion ?: $source->currentDraftVersion);
        if (! $version) {
            throw ValidationException::withMessages(['template' => 'The source has no copyable version.']);
        }
        $copy = $this->creator->create(new CreateWhatsAppMessageTemplateData($data->name, $source->description, $source->type->value, $version->body, $version->caption, $version->variable_context->value, $version->variable_configuration ?? []), $actor);
        if ($media = $version->attachment) {
            $attachment = new WhatsAppMessageTemplateAttachment;
            $attachment->forceFill(['tenant_id' => $copy->tenant_id, 'whatsapp_message_template_id' => $copy->id, 'whatsapp_message_template_version_id' => $copy->currentDraftVersion->id, 'disk' => $media->disk, 'storage_key' => $media->storage_key, 'original_name' => $media->original_name, 'safe_name' => $media->safe_name, 'mime_type' => $media->mime_type, 'extension' => $media->extension, 'size_bytes' => $media->size_bytes, 'checksum_sha256' => $media->checksum_sha256, 'media_category' => $media->media_category, 'width' => $media->width, 'height' => $media->height, 'duration_seconds' => $media->duration_seconds, 'created_by' => $actor->id])->save();
            $copy->currentDraftVersion->forceFill(['content_hash' => $version->content_hash])->save();
        }
        $this->audit->record('whatsapp_message_template.duplicated', $copy->load('tenant'), $copy->currentDraftVersion, $actor);

        return $copy;
    }
}
