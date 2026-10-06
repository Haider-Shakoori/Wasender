<?php

namespace App\Services\Templates;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Templates\RestoreWhatsAppMessageTemplateData;
use App\Enums\WhatsAppMessageTemplateStatus;
use App\Models\User;
use App\Models\WhatsAppMessageTemplate;
use Illuminate\Support\Facades\DB;

final class RestoreWhatsAppMessageTemplateService
{
    public function __construct(private TenantContext $context, private TenantEntitlements $entitlements, private WhatsAppMessageTemplateLifecycleGuard $guard, private TemplateAudit $audit) {}

    public function restore(WhatsAppMessageTemplate $template, RestoreWhatsAppMessageTemplateData $data, User $actor): WhatsAppMessageTemplate
    {
        $this->entitlements->requireFeature('whatsapp_message_templates');

        return DB::transaction(function () use ($template, $data, $actor): WhatsAppMessageTemplate {
            $template = WhatsAppMessageTemplate::forTenant($this->context->id())->whereKey($template->id)->lockForUpdate()->firstOrFail();
            if ($template->status !== WhatsAppMessageTemplateStatus::Archived) {
                return $template;
            }
            $this->guard->expected($template, $data->expectedVersion);
            $status = $template->current_published_version_id ? WhatsAppMessageTemplateStatus::Published : WhatsAppMessageTemplateStatus::Draft;
            $template->forceFill(['status' => $status, 'archived_at' => null, 'updated_by' => $actor->id, 'lock_version' => $template->lock_version + 1])->save();
            $this->audit->record('whatsapp_message_template.restored', $template->load('tenant'), $template->currentDraftVersion ?: $template->currentPublishedVersion, $actor, 'archived');

            return $template->refresh();
        });
    }
}
