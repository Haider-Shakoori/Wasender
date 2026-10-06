<?php

namespace App\Services\Templates;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Templates\PublishWhatsAppMessageTemplateData;
use App\Enums\WhatsAppMessageTemplateStatus;
use App\Enums\WhatsAppMessageTemplateVersionStatus;
use App\Models\User;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppMessageTemplateVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublishWhatsAppMessageTemplateService
{
    public function __construct(private TenantContext $context, private TenantEntitlements $entitlements, private WhatsAppMessageTemplateLifecycleGuard $guard, private ValidateWhatsAppMessageTemplateVersionService $validator, private TemplateAudit $audit) {}

    public function publish(WhatsAppMessageTemplate $template, PublishWhatsAppMessageTemplateData $data, User $actor): WhatsAppMessageTemplateVersion
    {
        $this->entitlements->requireFeature('whatsapp_message_templates');

        return DB::transaction(function () use ($template, $data, $actor): WhatsAppMessageTemplateVersion {
            $template = WhatsAppMessageTemplate::forTenant($this->context->id())->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $this->guard->editable($template);
            if (! $template->current_draft_version_id && $template->current_published_version_id) {
                $published = $template->currentPublishedVersion;
                if (! $published || $published->whatsapp_message_template_id !== $template->id || $published->status !== WhatsAppMessageTemplateVersionStatus::Published) {
                    throw ValidationException::withMessages(['version' => 'The published version pointer is invalid.']);
                }

                return $published;
            }
            $this->guard->expected($template, $data->expectedVersion);
            $draft = $template->currentDraftVersion;
            if (! $draft || $draft->whatsapp_message_template_id !== $template->id || $draft->status !== WhatsAppMessageTemplateVersionStatus::Draft) {
                throw ValidationException::withMessages(['version' => 'The active draft is invalid.']);
            }
            $draft->load('attachment');
            $validation = $this->validator->validate($draft, $template->type);
            if (! $validation->valid()) {
                throw ValidationException::withMessages(['template' => array_map(fn ($error) => $error['code'], $validation->errors)]);
            }
            if ($template->current_published_version_id) {
                WhatsAppMessageTemplateVersion::whereKey($template->current_published_version_id)->where('whatsapp_message_template_id', $template->id)->where('status', WhatsAppMessageTemplateVersionStatus::Published->value)->update(['status' => WhatsAppMessageTemplateVersionStatus::Superseded->value, 'superseded_at' => now(), 'updated_at' => now()]);
            } else {
                $this->entitlements->requireCapacity('whatsapp_message_templates.published_max');
            }
            $draft->forceFill(['status' => WhatsAppMessageTemplateVersionStatus::Published, 'content_hash' => $validation->contentHash, 'parser_version' => config('whatsapp_message_templates.parser_version'), 'renderer_version' => config('whatsapp_message_templates.renderer_version'), 'published_by' => $actor->id, 'published_at' => now()])->save();
            $previous = $template->status->value;
            $template->forceFill(['current_draft_version_id' => null, 'current_published_version_id' => $draft->id, 'status' => WhatsAppMessageTemplateStatus::Published, 'published_at' => now(), 'updated_by' => $actor->id, 'lock_version' => $template->lock_version + 1])->save();
            $this->audit->record('whatsapp_message_template.published', $template->load('tenant'), $draft, $actor, $previous);

            return $draft->refresh();
        });
    }
}
