<?php

namespace App\Services\Templates;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Templates\RemoveWhatsAppMessageTemplateAttachmentData;
use App\Enums\WhatsAppMessageTemplateVersionStatus;
use App\Models\User;
use App\Models\WhatsAppMessageTemplate;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RemoveWhatsAppMessageTemplateAttachmentService
{
    public function __construct(private TenantContext $context, private TenantEntitlements $entitlements, private WhatsAppMessageTemplateLifecycleGuard $guard, private ValidateWhatsAppMessageTemplateVersionService $validator, private StoreWhatsAppMessageTemplateAttachmentService $storage, private AuditService $audit) {}

    public function remove(WhatsAppMessageTemplate $template, RemoveWhatsAppMessageTemplateAttachmentData $data, User $actor): void
    {
        $this->entitlements->requireFeature('whatsapp_message_templates');
        $old = null;
        DB::transaction(function () use ($template, $data, $actor, &$old): void {
            $template = WhatsAppMessageTemplate::forTenant($this->context->id())->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $this->guard->editable($template);
            $this->guard->expected($template, $data->expectedVersion);
            $version = $template->currentDraftVersion;
            if (! $version || $version->status !== WhatsAppMessageTemplateVersionStatus::Draft) {
                throw ValidationException::withMessages(['attachment' => 'An active draft is required.']);
            }
            $old = $version->attachment;
            if (! $old) {
                return;
            } $old->delete();
            $version->setRelation('attachment', null);
            $result = $this->validator->validate($version, $template->type);
            $version->forceFill(['content_hash' => $result->contentHash])->save();
            $template->forceFill(['updated_by' => $actor->id, 'lock_version' => $template->lock_version + 1])->save();
            $this->audit->recordDomain('whatsapp_message_template.attachment_removed', $actor, $template->tenant, $template, ['template_uuid' => $template->uuid, 'template_version_uuid' => $version->uuid, 'attachment_uuid' => $old->uuid, 'media_category' => $old->media_category]);
        });
        if ($old) {
            $this->storage->deleteIfUnreferenced($old);
        }
    }
}
