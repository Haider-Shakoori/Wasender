<?php

namespace App\Listeners;

use App\Events\WhatsAppMessageStatusChanged;
use App\Services\Integrations\PublishIntegrationMessageWebhookService;

final class QueueIntegrationMessageWebhook
{
    public function __construct(private PublishIntegrationMessageWebhookService $publisher) {}

    public function handle(WhatsAppMessageStatusChanged $event): void
    {
        try {
            $this->publisher->publish($event->message);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
