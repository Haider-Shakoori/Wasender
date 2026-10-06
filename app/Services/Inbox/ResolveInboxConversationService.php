<?php

namespace App\Services\Inbox;

use App\Enums\WhatsAppConversationStatus;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppSession;

final class ResolveInboxConversationService
{
    public function resolve(Tenant $tenant, WhatsAppSession $session, Contact $contact, string $address, string $phone): WhatsAppConversation
    {
        $conversation = WhatsAppConversation::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'whatsapp_session_id' => $session->id, 'whatsapp_address' => $address],
            ['contact_id' => $contact->id, 'normalized_phone' => $phone, 'status' => WhatsAppConversationStatus::Open],
        );
        if (! $conversation->contact_id) {
            $conversation->update(['contact_id' => $contact->id]);
        }

        return $conversation;
    }
}
