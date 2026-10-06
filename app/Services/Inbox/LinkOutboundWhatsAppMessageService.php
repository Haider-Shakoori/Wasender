<?php

namespace App\Services\Inbox;

use App\Enums\WhatsAppInboxMediaStatus;
use App\Enums\WhatsAppInboxMessageDirection;
use App\Enums\WhatsAppInboxMessageStatus;
use App\Models\WhatsAppInboxMessage;
use App\Models\WhatsAppMessage;

final class LinkOutboundWhatsAppMessageService
{
    public function __construct(private ResolveInboxContactService $contacts, private ResolveInboxConversationService $conversations, private InboxMessagePreview $preview) {}

    public function link(WhatsAppMessage $outbound): WhatsAppInboxMessage
    {
        $outbound->loadMissing(['tenant', 'session']);
        $phone = ltrim($outbound->recipient_normalized, '+');
        $address = $phone.'@c.us';
        $contact = $this->contacts->resolve($outbound->tenant, $phone);
        $conversation = $this->conversations->resolve($outbound->tenant, $outbound->session, $contact, $address, '+'.$phone);
        $preview = $this->preview->make($outbound->message_type->value, $outbound->body);
        $sender = $outbound->session->wid && preg_match('/^[1-9][0-9]{7,14}@c\.us$/', $outbound->session->wid) ? $outbound->session->wid : ltrim((string) $outbound->session->phone_number, '+').'@c.us';
        $message = WhatsAppInboxMessage::query()->firstOrCreate(['outbound_message_id' => $outbound->id], [
            'tenant_id' => $outbound->tenant_id, 'whatsapp_conversation_id' => $conversation->id, 'whatsapp_session_id' => $outbound->whatsapp_session_id, 'contact_id' => $contact->id,
            'direction' => WhatsAppInboxMessageDirection::Outbound, 'message_type' => $outbound->message_type, 'status' => WhatsAppInboxMessageStatus::Queued,
            'sender_address' => $sender, 'recipient_address' => $address, 'body' => $outbound->body, 'message_preview' => $preview,
            'media_status' => $outbound->message_type->value === 'text' ? WhatsAppInboxMediaStatus::None : WhatsAppInboxMediaStatus::Available,
            'occurred_at' => $outbound->created_at ?? now(),
        ]);
        if (! $conversation->last_message_at || $message->occurred_at->gte($conversation->last_message_at)) {
            $conversation->update(['last_message_id' => $message->id, 'last_message_direction' => 'outbound', 'last_message_type' => $outbound->message_type->value, 'last_message_preview' => $preview, 'last_message_at' => $message->occurred_at, 'last_outbound_at' => $message->occurred_at, 'first_message_at' => $conversation->first_message_at ?? $message->occurred_at]);
        }

        return $message;
    }
}
