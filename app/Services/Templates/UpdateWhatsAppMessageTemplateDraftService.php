<?php

namespace App\Services\Templates;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Templates\UpdateWhatsAppMessageTemplateDraftData;
use App\Data\Templates\WhatsAppTemplateVariableConfiguration;
use App\Enums\TemplateVariableContext;
use App\Enums\WhatsAppMessageTemplateType;
use App\Enums\WhatsAppMessageTemplateVersionStatus;
use App\Models\User;
use App\Models\WhatsAppMessageTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateWhatsAppMessageTemplateDraftService
{
    public function __construct(private TenantContext $context, private TenantEntitlements $entitlements, private WhatsAppMessageTemplateLifecycleGuard $guard, private WhatsAppTemplateVariableRegistry $registry, private ValidateWhatsAppMessageTemplateVersionService $validator, private TemplateAudit $audit) {}

    public function update(WhatsAppMessageTemplate $template, UpdateWhatsAppMessageTemplateDraftData $data, User $actor): WhatsAppMessageTemplate
    {
        $this->entitlements->requireFeature('whatsapp_message_templates');

        return DB::transaction(function () use ($template, $data, $actor): WhatsAppMessageTemplate {
            $template = WhatsAppMessageTemplate::forTenant($this->context->id())->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $this->guard->editable($template);
            $this->guard->expected($template, $data->expectedVersion);
            $this->guard->typeChangeAllowed($template, $data->type);
            $draft = $template->currentDraftVersion;
            if (! $draft || $draft->whatsapp_message_template_id !== $template->id || $draft->status !== WhatsAppMessageTemplateVersionStatus::Draft) {
                throw ValidationException::withMessages(['version' => 'Create a draft before editing this template.']);
            }
            $variableContext = TemplateVariableContext::from($data->variableContext);
            $configuration = WhatsAppTemplateVariableConfiguration::normalize($data->variableConfiguration, $variableContext, $this->registry);
            if ($configuration->errors !== []) {
                throw ValidationException::withMessages(['variable_configuration' => array_column($configuration->errors, 'code')]);
            }
            $draft->forceFill(['body' => $data->body, 'caption' => $data->caption, 'variable_context' => $variableContext, 'variable_configuration' => $configuration->values, 'parser_version' => config('whatsapp_message_templates.parser_version'), 'renderer_version' => config('whatsapp_message_templates.renderer_version')])->save();
            $draft->load('attachment');
            $validation = $this->validator->validate($draft, WhatsAppMessageTemplateType::from($data->type));
            $draft->forceFill(['content_hash' => $validation->contentHash])->save();
            $template->forceFill(['name' => $data->name, 'description' => $data->description, 'type' => WhatsAppMessageTemplateType::from($data->type), 'updated_by' => $actor->id, 'lock_version' => $template->lock_version + 1])->save();
            $this->audit->record('whatsapp_message_template.updated', $template->load('tenant'), $draft, $actor);

            return $template->refresh()->load(['currentDraftVersion', 'currentPublishedVersion']);
        });
    }
}
