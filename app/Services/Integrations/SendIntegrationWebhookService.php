<?php

namespace App\Services\Integrations;

use App\Jobs\DeliverIntegrationWebhook;
use App\Models\Integration;
use App\Models\IntegrationWebhookDelivery;

final class SendIntegrationWebhookService
{
    public function __construct(private IntegrationUrlGuard $urls) {}

    public function send(Integration $integration, string $eventType, string $eventId, array $payload): IntegrationWebhookDelivery
    {
        abort_unless($integration->provider->value === 'webhook' && $integration->is_enabled && $integration->status === 'active', 422);
        abort_unless(in_array($eventType, config('integrations.outbound_events'), true), 422);
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        abort_if(strlen($encoded) > config('integrations.max_payload_bytes'), 413);
        $url = $this->urls->validate(data_get($integration->configuration, 'destination_url', ''));
        $delivery = IntegrationWebhookDelivery::firstOrCreate(['integration_id' => $integration->id, 'event_type' => $eventType, 'event_id' => $eventId], ['tenant_id' => $integration->tenant_id, 'status' => 'pending', 'payload_hash' => hash('sha256', $encoded)]);
        if ($delivery->wasRecentlyCreated) {
            DeliverIntegrationWebhook::dispatch($delivery->id, $url, $payload)->afterCommit();
        }

        return $delivery;
    }
}
