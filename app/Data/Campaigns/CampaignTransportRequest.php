<?php

namespace App\Data\Campaigns;

final readonly class CampaignTransportRequest
{
    public function __construct(public string $tenantUuid, public string $campaignUuid, public string $executionUuid, public string $recipientExecutionUuid, public string $dispatchAttemptUuid, public string $sessionUuid, public string $recipientPhone, public string $messageType, public ?string $body, public ?CampaignTransportAttachment $attachment, public string $idempotencyKey, public string $payloadHash, public array $metadata = []) {}

    public function toArray(string $transportRequestHash): array
    {
        return [
            'version' => 1,
            'tenant_uuid' => $this->tenantUuid,
            'campaign_uuid' => $this->campaignUuid,
            'execution_uuid' => $this->executionUuid,
            'recipient_execution_uuid' => $this->recipientExecutionUuid,
            'dispatch_attempt_uuid' => $this->dispatchAttemptUuid,
            'session_uuid' => $this->sessionUuid,
            'recipient' => ['phone_normalized' => $this->recipientPhone, 'whatsapp_address' => ltrim($this->recipientPhone, '+').'@c.us'],
            'message' => [
                'type' => $this->messageType,
                'body' => $this->messageType === 'text' ? $this->body : null,
                'caption' => $this->messageType !== 'text' ? $this->body : null,
                'attachment' => $this->attachment ? [
                    'uuid' => $this->attachment->uuid,
                    'retrieval_token' => $this->attachment->retrievalToken,
                    'retrieval_url' => $this->attachment->retrievalUrl,
                    'mime_type' => $this->attachment->mimeType,
                    'size_bytes' => $this->attachment->sizeBytes,
                    'checksum_sha256' => $this->attachment->checksumSha256,
                    'original_name' => $this->attachment->filename,
                ] : null,
            ],
            'attempt_number' => (int) $this->metadata['attempt_number'],
            'campaign_payload_hash' => $this->payloadHash,
            'transport_request_hash' => $transportRequestHash,
            'idempotency_key' => $this->idempotencyKey,
            'requested_at' => now()->utc()->toIso8601String(),
            'metadata' => ['source' => 'campaign'],
        ];
    }
}
