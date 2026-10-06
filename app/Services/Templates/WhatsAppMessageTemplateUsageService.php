<?php

namespace App\Services\Templates;

use App\Contracts\WhatsAppMessageTemplateUsageReference;
use App\Models\WhatsAppMessageTemplateUsage;
use App\Models\WhatsAppMessageTemplateVersion;

final class WhatsAppMessageTemplateUsageService implements WhatsAppMessageTemplateUsageReference
{
    public function record(WhatsAppMessageTemplateVersion $version, string $type, string $subjectUuid): void
    {
        WhatsAppMessageTemplateUsage::updateOrCreate(
            ['usage_type' => $type, 'usage_uuid' => $subjectUuid],
            ['tenant_id' => $version->template->tenant_id, 'whatsapp_message_template_id' => $version->whatsapp_message_template_id, 'whatsapp_message_template_version_id' => $version->id, 'template_uuid' => $version->template->uuid, 'template_version_uuid' => $version->uuid],
        );
    }

    public function countReferences(WhatsAppMessageTemplateVersion $version): int
    {
        return WhatsAppMessageTemplateUsage::where('template_version_uuid', $version->uuid)->count();
    }
}
