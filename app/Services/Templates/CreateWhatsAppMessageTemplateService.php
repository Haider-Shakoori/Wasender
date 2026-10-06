<?php

namespace App\Services\Templates;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Templates\CreateWhatsAppMessageTemplateData;
use App\Data\Templates\WhatsAppTemplateVariableConfiguration;
use App\Enums\TemplateVariableContext;
use App\Enums\WhatsAppMessageTemplateStatus;
use App\Enums\WhatsAppMessageTemplateType;
use App\Enums\WhatsAppMessageTemplateVersionStatus;
use App\Models\User;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppMessageTemplateVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateWhatsAppMessageTemplateService
{
    public function __construct(private TenantContext $context, private TenantEntitlements $entitlements, private WhatsAppTemplateVariableRegistry $registry, private ValidateWhatsAppMessageTemplateVersionService $validator, private TemplateAudit $audit) {}

    public function create(CreateWhatsAppMessageTemplateData $data, User $actor): WhatsAppMessageTemplate
    {
        $this->entitlements->requireFeature('whatsapp_message_templates');
        $this->entitlements->requireCapacity('whatsapp_message_templates.max');

        return DB::transaction(function () use ($data, $actor): WhatsAppMessageTemplate {
            $template = new WhatsAppMessageTemplate;
            $template->forceFill(['tenant_id' => $this->context->id(), 'name' => $data->name, 'description' => $data->description, 'type' => WhatsAppMessageTemplateType::from($data->type), 'status' => WhatsAppMessageTemplateStatus::Draft, 'created_by' => $actor->id, 'updated_by' => $actor->id])->save();
            $context = TemplateVariableContext::from($data->variableContext);
            $configuration = WhatsAppTemplateVariableConfiguration::normalize($data->variableConfiguration, $context, $this->registry);
            if ($configuration->errors !== []) {
                throw ValidationException::withMessages(['variable_configuration' => array_column($configuration->errors, 'code')]);
            }
            $version = new WhatsAppMessageTemplateVersion;
            $version->forceFill(['whatsapp_message_template_id' => $template->id, 'version_number' => 1, 'status' => WhatsAppMessageTemplateVersionStatus::Draft, 'body' => $data->body, 'caption' => $data->caption, 'content_configuration' => [], 'variable_context' => $context, 'variable_configuration' => $configuration->values, 'parser_version' => config('whatsapp_message_templates.parser_version'), 'renderer_version' => config('whatsapp_message_templates.renderer_version'), 'created_by' => $actor->id])->save();
            $version->setRelation('attachment', null);
            $validation = $this->validator->validate($version, $template->type);
            $version->forceFill(['content_hash' => $validation->contentHash])->save();
            $template->forceFill(['current_draft_version_id' => $version->id])->save();
            $this->audit->record('whatsapp_message_template.created', $template->load('tenant'), $version, $actor);

            return $template->refresh()->load(['currentDraftVersion', 'currentPublishedVersion']);
        });
    }
}
