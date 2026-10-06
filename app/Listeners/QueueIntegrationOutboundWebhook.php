<?php

namespace App\Listeners;

use App\Events\InboxConversationCreated;
use App\Events\InboxMessageReceived;
use App\Models\Integration;
use App\Services\Integrations\SendIntegrationWebhookService;

final class QueueIntegrationOutboundWebhook
{
    public function __construct(private SendIntegrationWebhookService $webhooks) {}

    public function handle(InboxMessageReceived|InboxConversationCreated $event): void
    {
        $subject = $event instanceof InboxMessageReceived ? $event->message : $event->conversation;
        $type = $event instanceof InboxMessageReceived ? 'inbox.message.received' : 'inbox.conversation.created';
        foreach (Integration::forTenant($subject->tenant_id)->where('status', 'active')->where('is_enabled', true)->get() as $integration) {
            if (! filled(data_get($integration->configuration, 'destination_url'))) {
                continue;
            }
            try {
                $this->webhooks->send($integration, $type, $subject->uuid, ['resource_uuid' => $subject->uuid, 'occurred_at' => now()->toIso8601String()]);
            } catch (\Throwable $exception) {
                report($exception);
                $integration->update(['last_failure_at' => now(), 'last_failure_code' => 'delivery_configuration_invalid']);
            }
        }
    }
}
