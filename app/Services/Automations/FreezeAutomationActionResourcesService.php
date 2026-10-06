<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Models\AutomationWorkflowVersion;
use App\Models\WhatsAppMessageTemplate;
use Illuminate\Validation\ValidationException;

final class FreezeAutomationActionResourcesService
{
    public function __construct(private TenantContext $tenant) {}

    public function freeze(AutomationWorkflowVersion $version): void
    {
        $version->loadMissing('steps');
        foreach ($version->steps as $step) {
            $c = $step->configuration ?? [];
            if ($step->step_type->value !== 'action' || ($c['action_type'] ?? null) !== 'send_whatsapp_template') {
                continue;
            }$p = $c['parameters'] ?? [];
            $template = WhatsAppMessageTemplate::forTenant($this->tenant->id())->where('uuid', $p['template_uuid'] ?? '')->whereNull('archived_at')->with('currentPublishedVersion')->first();
            $published = $template?->currentPublishedVersion;
            if (! $published || ! $published->content_hash) {
                throw ValidationException::withMessages(['workflow' => 'template_not_published']);
            }$p['template_version_uuid'] = $published->uuid;
            $p['template_version_number'] = $published->version_number;
            $p['template_content_hash'] = $published->content_hash;
            $c['parameters'] = $p;
            $step->forceFill(['configuration' => $c])->save();
        }$version->unsetRelation('steps');
    }
}
