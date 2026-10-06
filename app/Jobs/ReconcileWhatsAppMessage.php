<?php

namespace App\Jobs;

use App\Contracts\Messaging\MessagingConnector;
use App\Enums\WhatsAppMessageStatus;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppMessageLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use RuntimeException;

final class ReconcileWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(public int $messageId)
    {
        $this->onQueue(config('whatsapp_messages.queue'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("wa:reconcile:{$this->messageId}"))->expireAfter(60)];
    }

    public function handle(MessagingConnector $connector, WhatsAppMessageLifecycleService $lifecycle): void
    {
        $message = WhatsAppMessage::findOrFail($this->messageId);
        if ($message->status !== WhatsAppMessageStatus::Failed || $message->failure_code !== 'ambiguous_transport' || ! $message->connector_request_id) {
            return;
        }
        $result = $connector->reconcile($message->connector_request_id);
        if ($result->accepted && $result->externalId) {
            $lifecycle->transition($message, WhatsAppMessageStatus::Sent, 'system', ['whatsapp_message_id' => $result->externalId]);

            return;
        }

        if ($result->retryable) {
            throw new RuntimeException($result->errorCode ?? 'reconciliation_unavailable');
        }
    }
}
