<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use App\Models\WhatsAppMessage;

final class PublishIntegrationMessageWebhookService
{
    public function __construct(private SendIntegrationWebhookService $webhooks) {}

    public function publish(WhatsAppMessage $message): void
    {
        $integrationUuid = data_get($message->metadata, 'integration_uuid');
        if (data_get($message->metadata, 'source') !== 'integration' || ! is_string($integrationUuid) || $integrationUuid === '') {
            return;
        }

        $eventType = match ($message->status->value) {
            'queued' => 'message.queued',
            'sent' => 'message.sent',
            'delivered' => 'message.delivered',
            'read' => 'message.read',
            'failed', 'cancelled', 'expired' => 'message.failed',
            default => null,
        };

        if (! $eventType) {
            return;
        }

        $integration = Integration::query()
            ->where('tenant_id', $message->tenant_id)
            ->where('uuid', $integrationUuid)
            ->where('status', 'active')
            ->where('is_enabled', true)
            ->first();

        if (! $integration || ! filled(data_get($integration->configuration, 'destination_url'))) {
            return;
        }

        $this->webhooks->send($integration, $eventType, $message->uuid.':'.$message->status->value, [
            'message_uuid' => $message->uuid,
            'status' => $message->status->value,
            'recipient' => $message->recipient_normalized,
            'whatsapp_message_id' => $message->whatsapp_message_id,
            'failure_code' => $message->failure_code,
            'failure_retryable' => (bool) $message->failure_retryable,
            'queued_at' => $message->queued_at?->toIso8601String(),
            'sent_at' => $message->sent_at?->toIso8601String(),
            'delivered_at' => $message->delivered_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'failed_at' => $message->failed_at?->toIso8601String(),
        ]);
    }
}
