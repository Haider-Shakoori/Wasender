<?php

namespace App\Jobs;

use App\Contracts\Messaging\MessagingConnector;
use App\Data\Messaging\MessageData;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppSessionStatus;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageAttempt;
use App\Services\WhatsAppMessageLifecycleService;
use App\Services\WhatsAppSessionPacer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Str;
use Throwable;

final class DispatchWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $messageId)
    {
        $this->onQueue(config('whatsapp_messages.queue'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("wa:message:{$this->messageId}"))->expireAfter(120)];
    }

    public function handle(MessagingConnector $connector, WhatsAppMessageLifecycleService $lifecycle, WhatsAppSessionPacer $pacer): void
    {
        $message = WhatsAppMessage::with(['session', 'attachment'])->findOrFail($this->messageId);
        if ($message->status !== WhatsAppMessageStatus::Queued) {
            return;
        }
        if ($message->expires_at?->isPast()) {
            $lifecycle->transition($message, WhatsAppMessageStatus::Expired, 'queue');

            return;
        }
        if ($message->session->status !== WhatsAppSessionStatus::Ready) {
            $lifecycle->transition($message, WhatsAppMessageStatus::Failed, 'queue', ['failure_code' => 'session_not_ready', 'failure_message' => 'The selected WhatsApp session is not connected.', 'failure_retryable' => true]);

            return;
        }

        $waitMs = $pacer->reserve($message->session);
        if ($waitMs > 0) {
            self::dispatch($message->id)->delay(now()->addMilliseconds($waitMs));

            return;
        }

        $message = $lifecycle->transition($message, WhatsAppMessageStatus::Processing, 'queue');
        $requestId = (string) Str::uuid();
        $attempt = $message->attempts + 1;
        $message->update(['attempts' => $attempt, 'last_attempt_at' => now(), 'connector_request_id' => $requestId]);
        WhatsAppMessageAttempt::create(['tenant_id' => $message->tenant_id, 'whatsapp_message_id' => $message->id, 'attempt_number' => $attempt, 'connector_request_id' => $requestId, 'started_at' => now(), 'status' => 'sending', 'created_at' => now()]);
        $message = $lifecycle->transition($message->refresh(), WhatsAppMessageStatus::Sending, 'queue');
        $media = $message->attachment ? ['url' => url("/internal/whatsapp/messages/{$message->uuid}/media?request_id={$requestId}"), 'mime_type' => $message->attachment->mime_type,
            'filename' => $message->attachment->safe_name, 'size' => $message->attachment->size_bytes, 'checksum_sha256' => $message->attachment->checksum_sha256] : null;
        try {
            $result = $connector->send(new MessageData($message->uuid, $message->session->uuid, $message->session->storage_key, $requestId,
                $message->recipient_normalized, $message->message_type->value, $message->body, $media, $message->expires_at?->toIso8601String()));
            if (! $result->accepted || ! $result->externalId) {
                $lifecycle->transition($message->refresh(), WhatsAppMessageStatus::Failed, 'connector', ['failure_code' => $result->errorCode ?? 'send_rejected', 'failure_message' => 'The connector rejected this message.', 'failure_retryable' => $result->retryable]);

                return;
            }
            $lifecycle->transition($message->refresh(), WhatsAppMessageStatus::Sent, 'connector', ['whatsapp_message_id' => $result->externalId]);
            WhatsAppMessageAttempt::where('connector_request_id', $requestId)->update(['completed_at' => now(), 'status' => 'sent']);
        } catch (Throwable) {
            $failed = $lifecycle->transition($message->refresh(), WhatsAppMessageStatus::Failed, 'connector', ['failure_code' => 'ambiguous_transport', 'failure_message' => 'The send result is uncertain and requires reconciliation.', 'failure_retryable' => false]);
            ReconcileWhatsAppMessage::dispatch($failed->id)->delay(now()->addSeconds(10));
        }
    }
}
