<?php

namespace App\Services\Campaigns;

use App\Data\Campaigns\CampaignTransportAttachment;
use App\Data\Campaigns\CampaignTransportRequest;
use App\Models\WhatsAppCampaignDispatchAttempt;
use App\Models\WhatsAppCampaignRecipientExecution;
use App\Models\WhatsAppSession;

final class CampaignTransportRequestBuilder
{
    public function __construct(private BuildCampaignAttachmentRetrievalTokenService $tokens) {}

    public function build(WhatsAppCampaignRecipientExecution $recipientExecution, WhatsAppCampaignDispatchAttempt $attempt, WhatsAppSession $session): CampaignTransportRequest
    {
        $execution = $recipientExecution->execution;
        $campaign = $execution->campaign;
        $snapshot = $recipientExecution->recipient;
        $attachment = $campaign->attachment;
        $media = $attachment ? new CampaignTransportAttachment(
            $attachment->uuid,
            $attachment->mime_type,
            $attachment->safe_name,
            $attachment->size_bytes,
            $attachment->checksum_sha256,
            $this->tokens->build($attachment, $attempt),
            rtrim((string) config('app.url'), '/').route('internal.whatsapp.campaign-attachment', ['attachmentUuid' => $attachment->uuid], false),
        ) : null;

        $body = $campaign->message_type->value === 'text'
            ? ($snapshot->rendered_body ?? $campaign->body)
            : ($snapshot->rendered_caption ?? $campaign->body);

        return new CampaignTransportRequest($execution->tenant->uuid, $campaign->uuid, $execution->uuid, $recipientExecution->uuid, $attempt->uuid, $session->uuid, $snapshot->phone_normalized, $campaign->message_type->value, $body, $media, $attempt->idempotency_key, $campaign->payload_hash, ['attempt_number' => $attempt->attempt_number, 'template_render_hash' => $snapshot->template_render_hash]);
    }

    public function hash(CampaignTransportRequest $r): string
    {
        return hash('sha256', json_encode([$r->tenantUuid, $r->campaignUuid, $r->executionUuid, $r->recipientExecutionUuid, $r->dispatchAttemptUuid, $r->sessionUuid, $r->recipientPhone, $r->messageType, hash('sha256', (string) $r->body), $r->attachment?->checksumSha256, $r->payloadHash, $r->metadata['attempt_number']], JSON_THROW_ON_ERROR));
    }
}
