<?php

namespace App\Services\Templates;

use App\Models\User;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppMessageTemplateVersion;
use App\Services\AuditService;

final class TemplateAudit
{
    public function __construct(private AuditService $audit) {}

    public function record(string $action, WhatsAppMessageTemplate $template, ?WhatsAppMessageTemplateVersion $version, User $actor, ?string $previous = null): void
    {
        $this->audit->recordDomain($action, $actor, $template->tenant, $template, [
            'template_uuid' => $template->uuid, 'version_uuid' => $version?->uuid,
            'version_number' => $version?->version_number, 'template_type' => $template->type->value,
            'previous_status' => $previous, 'new_status' => $template->status->value,
        ]);
    }
}
