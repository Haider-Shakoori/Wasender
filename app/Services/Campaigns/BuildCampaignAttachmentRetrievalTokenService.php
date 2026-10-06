<?php

namespace App\Services\Campaigns;

use App\Models\WhatsAppCampaignAttachment;
use App\Models\WhatsAppCampaignDispatchAttempt;
use Illuminate\Support\Facades\Crypt;

final class BuildCampaignAttachmentRetrievalTokenService
{
    public function build(WhatsAppCampaignAttachment $attachment, WhatsAppCampaignDispatchAttempt $attempt): string
    {
        return Crypt::encryptString(json_encode([
            'purpose' => 'campaign-attachment',
            'tenant_id' => $attachment->tenant_id,
            'campaign_id' => $attachment->whatsapp_campaign_id,
            'execution_id' => $attempt->campaign_execution_id,
            'attempt_uuid' => $attempt->uuid,
            'attachment_uuid' => $attachment->uuid,
            'checksum' => $attachment->checksum_sha256,
            'expires_at' => now()->addSeconds(config('whatsapp_campaign_transport.attachment_token_ttl_seconds'))->timestamp,
        ], JSON_THROW_ON_ERROR));
    }
}
