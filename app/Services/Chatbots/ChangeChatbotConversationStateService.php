<?php

namespace App\Services\Chatbots;

use App\Models\User;
use App\Models\WhatsAppChatbot;
use App\Models\WhatsAppChatbotConversationState;
use App\Models\WhatsAppConversation;
use App\Services\Inbox\ConversationActivityRecorder;

final class ChangeChatbotConversationStateService
{
    public function __construct(private ConversationActivityRecorder $activity) {}

    public function change(WhatsAppConversation $conversation, WhatsAppChatbot $chatbot, string $state, ?User $actor = null): WhatsAppChatbotConversationState
    {
        $record = WhatsAppChatbotConversationState::updateOrCreate(['chatbot_id' => $chatbot->id, 'whatsapp_conversation_id' => $conversation->id], ['tenant_id' => $conversation->tenant_id, 'contact_id' => $conversation->contact_id, 'state' => $state, 'handoff_active' => $state === 'handoff', 'cooldown_until' => null]);
        $this->activity->record($conversation, $actor, 'chatbot_'.$state, ['chatbot_uuid' => $chatbot->uuid], $chatbot->uuid);

        return $record;
    }
}
