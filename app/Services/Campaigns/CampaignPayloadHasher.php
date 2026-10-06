<?php

namespace App\Services\Campaigns;

use App\Data\Campaigns\CampaignPayload;
use App\Models\WhatsAppCampaign;

final class CampaignPayloadHasher
{
    public function for(WhatsAppCampaign $c, ?string $attachmentChecksum = null): string
    {
        return hash('sha256', json_encode((new CampaignPayload([
            'message_type' => $c->message_type->value, 'body' => $c->body, 'attachment_checksum' => $attachmentChecksum ?? $c->attachment?->checksum_sha256,
            'template' => [$c->template_uuid, $c->template_version_uuid, $c->template_version_number, $c->template_content_hash, $c->template_variable_values],
            'audience_type' => $c->audience_type->value, 'audience_config' => $c->audience_config ?? [], 'session_strategy' => $c->session_strategy->value,
            'session_config' => $c->session_config ?? [], 'schedule_type' => $c->schedule_type->value, 'scheduled_at_utc' => $c->scheduled_at_utc?->toIso8601String(),
            'timezone' => $c->timezone, 'send_window' => $c->send_window_config ?? [], 'execution' => $c->execution_config ?? [],
        ]))->canonical(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function array(array $values): string
    {
        return hash('sha256', json_encode((new CampaignPayload($values))->canonical(), JSON_THROW_ON_ERROR));
    }
}
