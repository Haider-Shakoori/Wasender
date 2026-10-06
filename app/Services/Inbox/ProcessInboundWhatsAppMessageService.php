<?php

namespace App\Services\Inbox;

use App\Data\Inbox\InboundWhatsAppMessageEventData;
use App\Data\Inbox\InboxMessageProcessingResult;
use App\Enums\WhatsAppConversationStatus;
use App\Enums\WhatsAppInboxMediaStatus;
use App\Enums\WhatsAppInboxMessageDirection;
use App\Enums\WhatsAppInboxMessageStatus;
use App\Events\InboxConversationCreated;
use App\Events\InboxConversationUpdated;
use App\Events\InboxMessageReceived;
use App\Models\Tenant;
use App\Models\WhatsAppInboxMessage;
use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProcessInboundWhatsAppMessageService
{
    public function __construct(private ResolveInboxContactService $contacts, private ResolveInboxConversationService $conversations, private InboxMessagePreview $preview) {}

    public function process(InboundWhatsAppMessageEventData $event): InboxMessageProcessingResult
    {
        return DB::transaction(function () use ($event): InboxMessageProcessingResult {
            $prior = DB::table('whatsapp_callback_events')->where('event_uuid', $event->eventId)->lockForUpdate()->first();
            if ($prior) {
                if (! $prior->payload_hash || ! hash_equals($prior->payload_hash, $event->payloadHash)) {
                    throw ValidationException::withMessages(['event_id' => 'Event ID was already used with a different payload.']);
                }
                $result = json_decode((string) $prior->result, true, flags: JSON_THROW_ON_ERROR);

                return new InboxMessageProcessingResult($result['conversation_uuid'], $result['message_uuid'], true);
            }

            $tenant = Tenant::query()->where('uuid', $event->tenantUuid)->firstOrFail();
            $session = WhatsAppSession::query()->where('uuid', $event->sessionUuid)->where('tenant_id', $tenant->id)->firstOrFail();
            $phone = str($event->message->from)->before('@')->toString();
            $contact = $this->contacts->resolve($tenant, $phone);
            $conversation = $this->conversations->resolve($tenant, $session, $contact, $event->message->from, '+'.$phone);
            $wasRecentlyCreated = $conversation->wasRecentlyCreated;
            $conversation = $conversation->newQuery()->lockForUpdate()->findOrFail($conversation->id);

            $existing = WhatsAppInboxMessage::query()->where('whatsapp_session_id', $session->id)->where('whatsapp_message_id', $event->message->whatsappMessageId)->first();
            if ($existing) {
                $result = new InboxMessageProcessingResult($existing->conversation->uuid, $existing->uuid, true);
                DB::table('whatsapp_callback_events')->insert(['event_uuid' => $event->eventId, 'whatsapp_session_id' => $session->id, 'event' => 'inbox.message.received', 'payload_hash' => $event->payloadHash, 'result' => json_encode($result->toArray(), JSON_THROW_ON_ERROR), 'processed_at' => now()]);

                return $result;
            }

            $reply = $event->message->replyToMessageId ? WhatsAppInboxMessage::query()->where('whatsapp_session_id', $session->id)->where('whatsapp_message_id', $event->message->replyToMessageId)->first() : null;
            $preview = $this->preview->make($event->message->type, $event->message->body, $event->message->caption);
            $message = WhatsAppInboxMessage::query()->create([
                'tenant_id' => $tenant->id, 'whatsapp_conversation_id' => $conversation->id, 'whatsapp_session_id' => $session->id, 'contact_id' => $contact->id,
                'direction' => WhatsAppInboxMessageDirection::Inbound, 'message_type' => $event->message->type, 'status' => WhatsAppInboxMessageStatus::Received,
                'whatsapp_message_id' => $event->message->whatsappMessageId, 'whatsapp_serialized_id' => $event->message->serializedId,
                'reply_to_whatsapp_message_id' => $event->message->replyToMessageId, 'reply_to_message_id' => $reply?->id, 'sender_address' => $event->message->from, 'recipient_address' => $event->message->to,
                'body' => $event->message->body, 'caption' => $event->message->caption, 'message_preview' => $preview,
                'media_status' => $event->message->media ? WhatsAppInboxMediaStatus::Pending : WhatsAppInboxMediaStatus::None,
                'media_mime_type' => $event->message->media?->mimeType, 'media_size_bytes' => $event->message->media?->sizeBytes, 'media_checksum_sha256' => $event->message->media?->checksumSha256,
                'media_retrieval_reference' => $event->message->media?->retrievalReference, 'occurred_at' => $event->message->timestamp, 'received_at' => now(),
            ]);
            $newer = ! $conversation->last_message_at || $event->message->timestamp->gte($conversation->last_message_at);
            $lastInboundAt = ! $conversation->last_inbound_at || $event->message->timestamp->gte($conversation->last_inbound_at) ? $event->message->timestamp : $conversation->last_inbound_at;
            $inboundStatus = $conversation->status === WhatsAppConversationStatus::Archived ? WhatsAppConversationStatus::Archived : WhatsAppConversationStatus::Open;
            $changes = ['contact_id' => $contact->id, 'status' => $inboundStatus, 'last_inbound_at' => $lastInboundAt, 'first_message_at' => $conversation->first_message_at ?? $event->message->timestamp];
            if ($conversation->status === WhatsAppConversationStatus::Closed) {
                $changes += ['closed_at' => null, 'closed_by' => null];
            }
            if ($newer) {
                $changes += ['last_message_id' => $message->id, 'last_message_direction' => 'inbound', 'last_message_type' => $event->message->type, 'last_message_preview' => $preview, 'last_message_at' => $event->message->timestamp];
            }
            $conversation->fill($changes);
            $conversation->increment('unread_count', 1, $conversation->getDirty());
            $result = new InboxMessageProcessingResult($conversation->uuid, $message->uuid);
            DB::table('whatsapp_callback_events')->insert(['event_uuid' => $event->eventId, 'whatsapp_session_id' => $session->id, 'event' => 'inbox.message.received', 'payload_hash' => $event->payloadHash, 'result' => json_encode($result->toArray(), JSON_THROW_ON_ERROR), 'processed_at' => now()]);
            if ($wasRecentlyCreated) {
                InboxConversationCreated::dispatch($conversation);
            }
            InboxMessageReceived::dispatch($message);
            InboxConversationUpdated::dispatch($conversation);

            return $result;
        }, 3);
    }
}
