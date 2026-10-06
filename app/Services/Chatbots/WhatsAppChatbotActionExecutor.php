<?php

namespace App\Services\Chatbots;

use App\Enums\WhatsAppChatbotActionType;
use App\Models\User;
use App\Models\WhatsAppChatbot;
use App\Models\WhatsAppChatbotRule;
use App\Models\WhatsAppInboxMessage;
use App\Models\WhatsAppMessage;
use App\Services\Templates\CreateTransactionalWhatsAppMessageFromTemplateService;
use App\Services\WhatsAppMessageService;

final class WhatsAppChatbotActionExecutor
{
    public function __construct(private WhatsAppMessageService $messages, private CreateTransactionalWhatsAppMessageFromTemplateService $templates, private ChangeChatbotConversationStateService $states) {}

    public function execute(WhatsAppChatbot $bot, ?WhatsAppChatbotRule $rule, WhatsAppInboxMessage $inbound, User $actor, string $actionType, array $configuration): ?WhatsAppMessage
    {
        $conversation = $inbound->conversation;
        $contact = $inbound->contact;
        $key = hash('sha256', implode('|', [$bot->uuid, $rule?->uuid ?? 'fallback', $inbound->uuid, $conversation->uuid, $configuration['template_version_uuid'] ?? '', $configuration['text'] ?? '']));
        if ($actionType === WhatsAppChatbotActionType::ReplyText->value) {
            return $this->messages->create($bot->tenant, $actor, ['session_uuid' => $inbound->session->uuid, 'recipient' => $conversation->normalized_phone, 'message_type' => 'text', 'body' => $configuration['text'], 'idempotency_key' => $key, 'metadata' => ['source' => 'chatbot', 'chatbot_uuid' => $bot->uuid, 'inbound_message_uuid' => $inbound->uuid]], null);
        }
        if ($actionType === WhatsAppChatbotActionType::ReplyTemplate->value) {
            return $this->templates->create($bot->tenant, $actor, [
                'session_uuid' => $inbound->session->uuid,
                'recipient' => $conversation->normalized_phone,
                'template_uuid' => $configuration['template_uuid'],
                'template_version_uuid' => $configuration['template_version_uuid'],
                'values' => array_filter([
                    'first_name' => $contact?->first_name,
                    'last_name' => $contact?->last_name,
                    'company' => $contact?->company,
                    'preferred_language' => $contact?->preferred_language,
                ], fn ($v) => $v !== null),
                'timezone' => $bot->tenant->timezone,
                'idempotency_key' => $key,
                'metadata' => [
                    'source' => 'chatbot',
                    'chatbot_uuid' => $bot->uuid,
                    'rule_uuid' => $rule?->uuid,
                    'inbound_message_uuid' => $inbound->uuid,
                ],
            ]);
        }
        $this->states->change($conversation, $bot, $actionType === WhatsAppChatbotActionType::Handoff->value ? 'handoff' : 'stopped');
        if ($actionType === WhatsAppChatbotActionType::Handoff->value && $conversation->status->value !== 'archived') {
            $conversation->update(['status' => 'open']);
        }

        return null;
    }
}
