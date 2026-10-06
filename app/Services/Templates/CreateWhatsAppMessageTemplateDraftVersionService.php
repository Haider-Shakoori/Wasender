<?php

namespace App\Services\Templates;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Templates\CreateDraftFromPublishedTemplateData;
use App\Enums\WhatsAppMessageTemplateVersionStatus;
use App\Models\User;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppMessageTemplateAttachment;
use App\Models\WhatsAppMessageTemplateVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateWhatsAppMessageTemplateDraftVersionService
{
    public function __construct(private TenantContext $context, private TenantEntitlements $entitlements, private WhatsAppMessageTemplateLifecycleGuard $guard, private TemplateAudit $audit) {}

    public function create(WhatsAppMessageTemplate $template, CreateDraftFromPublishedTemplateData $data, User $actor): WhatsAppMessageTemplateVersion
    {
        $this->entitlements->requireFeature('whatsapp_message_templates');

        return DB::transaction(function () use ($template, $data, $actor): WhatsAppMessageTemplateVersion {
            $template = WhatsAppMessageTemplate::forTenant($this->context->id())->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $this->guard->editable($template);
            if ($template->current_draft_version_id) {
                $active = $template->currentDraftVersion;
                if (! $active || $active->whatsapp_message_template_id !== $template->id || $active->status !== WhatsAppMessageTemplateVersionStatus::Draft) {
                    throw ValidationException::withMessages(['version' => 'The active draft pointer is invalid.']);
                }

                return $active;
            }
            $this->guard->expected($template, $data->expectedVersion);
            $published = $template->currentPublishedVersion;
            if (! $published) {
                throw ValidationException::withMessages(['template' => 'A published version is required.']);
            }
            $next = ((int) $template->versions()->max('version_number')) + 1;
            $draft = new WhatsAppMessageTemplateVersion;
            $draft->forceFill(['whatsapp_message_template_id' => $template->id, 'version_number' => $next, 'status' => WhatsAppMessageTemplateVersionStatus::Draft, 'body' => $published->body, 'caption' => $published->caption, 'content_configuration' => $published->content_configuration ?? [], 'variable_context' => $published->variable_context, 'variable_configuration' => $published->variable_configuration ?? [], 'content_hash' => $published->content_hash, 'parser_version' => $published->parser_version, 'renderer_version' => $published->renderer_version, 'created_by' => $actor->id])->save();
            if ($media = $published->attachment) {
                $copy = new WhatsAppMessageTemplateAttachment;
                $copy->forceFill(['tenant_id' => $template->tenant_id, 'whatsapp_message_template_id' => $template->id, 'whatsapp_message_template_version_id' => $draft->id, 'disk' => $media->disk, 'storage_key' => $media->storage_key, 'original_name' => $media->original_name, 'safe_name' => $media->safe_name, 'mime_type' => $media->mime_type, 'extension' => $media->extension, 'size_bytes' => $media->size_bytes, 'checksum_sha256' => $media->checksum_sha256, 'media_category' => $media->media_category, 'width' => $media->width, 'height' => $media->height, 'duration_seconds' => $media->duration_seconds, 'created_by' => $actor->id])->save();
                $draft->setRelation('attachment', $copy);
            }
            $template->forceFill(['current_draft_version_id' => $draft->id, 'updated_by' => $actor->id, 'lock_version' => $template->lock_version + 1])->save();
            $this->audit->record('whatsapp_message_template.draft_created', $template->load('tenant'), $draft, $actor);

            return $draft->refresh();
        });
    }
}
