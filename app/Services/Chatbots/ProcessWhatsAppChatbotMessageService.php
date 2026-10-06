<?php

namespace App\Services\Chatbots;

use App\Enums\WhatsAppChatbotStatus;
use App\Models\TenantMembership;
use App\Models\WhatsAppChatbot;
use App\Models\WhatsAppChatbotConversationState;
use App\Models\WhatsAppChatbotRuleExecution;
use App\Models\WhatsAppInboxMessage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ProcessWhatsAppChatbotMessageService
{
    public function __construct(private WhatsAppChatbotMatcher $matcher, private WhatsAppChatbotActionExecutor $actions) {}

    public function process(int $messageId): void
    {
        $message = WhatsAppInboxMessage::with(['tenant', 'session', 'conversation', 'contact'])->find($messageId);
        if (! $message || $message->direction->value !== 'inbound' || $message->message_type->value !== 'text' || blank($message->body) || $message->conversation->status->value === 'archived' || $this->nonDirect((string) $message->sender_address)) {
            return;
        }
        $bot = WhatsAppChatbot::query()->where('whatsapp_chatbots.tenant_id', $message->tenant_id)->where('status', WhatsAppChatbotStatus::Published)->where('is_enabled', true)->whereHas('sessions', fn ($q) => $q->where('whatsapp_sessions.id', $message->whatsapp_session_id))->with(['tenant', 'creator', 'rules' => fn ($q) => $q->where('is_enabled', true)->orderBy('priority')->orderBy('id')])->orderBy('priority')->first();
        if (! $bot) {
            return;
        }
        DB::table('whatsapp_chatbot_conversation_states')->insertOrIgnore(['uuid' => (string) Str::uuid(), 'tenant_id' => $message->tenant_id, 'chatbot_id' => $bot->id, 'whatsapp_conversation_id' => $message->whatsapp_conversation_id, 'contact_id' => $message->contact_id, 'state' => 'active', 'handoff_active' => false, 'created_at' => now(), 'updated_at' => now()]);
        $state = WhatsAppChatbotConversationState::where('chatbot_id', $bot->id)->where('whatsapp_conversation_id', $message->whatsapp_conversation_id)->firstOrFail();
        if ($state->handoff_active || in_array($state->state, ['handoff', 'stopped'], true) || $state->cooldown_until?->isFuture()) {
            $this->record($bot, $message, null, null, 'skipped', $state->handoff_active ? 'conversation_handoff' : ($state->state === 'stopped' ? 'conversation_stopped' : 'rate_limited'));

            return;
        }
        $recent = WhatsAppChatbotRuleExecution::where('chatbot_id', $bot->id)->where('whatsapp_conversation_id', $message->whatsapp_conversation_id)->where('status', 'replied')->where('processed_at', '>=', now()->subMinute())->count();
        if ($recent >= config('chatbots.max_replies_per_conversation_per_minute')) {
            $this->record($bot, $message, null, null, 'skipped', 'rate_limited');

            return;
        }
        $rule = $bot->rules->first(fn ($rule) => $this->matcher->matches($rule, (string) $message->body, $message->contact));
        $action = $rule?->action_type->value;
        $configuration = (array) $rule?->action_configuration;
        if (! $rule) {
            $action = match ($bot->fallback_behavior) {
                'reply_text' => 'reply_text', 'reply_template' => 'reply_template', 'handoff' => 'handoff_to_human', default => null
            };
            $configuration = (array) $bot->fallback_configuration;
        }
        if (! $action) {
            $this->record($bot, $message, null, null, 'skipped', 'no_rule_matched');

            return;
        }
        $execution = $this->record($bot, $message, $rule, $action, 'matched', null);
        if (! $execution || $execution->status !== 'matched') {
            return;
        }
        $actor = $bot->creator ?? TenantMembership::forTenant($bot->tenant_id)->active()->with('user')->first()?->user;
        if (! $actor) {
            $execution->update(['status' => 'failed', 'failure_code' => 'internal_error']);

            return;
        }
        try {
            $outbound = $this->actions->execute($bot, $rule, $message, $actor, $action, $configuration);
            $status = match ($action) {
                'handoff_to_human' => 'handoff', 'stop_bot' => 'stopped', default => 'replied'
            };
            $execution->update(['outbound_message_id' => $outbound?->id, 'status' => $status, 'processed_at' => now()]);
            $state->update(['last_rule_id' => $rule?->id, 'last_processed_message_id' => $message->id, 'last_bot_reply_at' => $outbound ? now() : $state->last_bot_reply_at, 'cooldown_until' => $outbound ? now()->addSeconds(config('chatbots.default_cooldown_seconds')) : null]);
        } catch (Throwable $e) {
            report($e);
            $execution->update(['status' => 'failed', 'failure_code' => $this->failureCode($e), 'processed_at' => now()]);
        }
    }

    private function record(WhatsAppChatbot $bot, WhatsAppInboxMessage $message, $rule, ?string $action, string $status, ?string $failure): ?WhatsAppChatbotRuleExecution
    {
        try {
            return WhatsAppChatbotRuleExecution::create(['tenant_id' => $bot->tenant_id, 'chatbot_id' => $bot->id, 'rule_id' => $rule?->id, 'whatsapp_conversation_id' => $message->whatsapp_conversation_id, 'inbound_message_id' => $message->id, 'action_type' => $action, 'status' => $status, 'failure_code' => $failure, 'processed_at' => now()]);
        } catch (QueryException) {
            return WhatsAppChatbotRuleExecution::where('chatbot_id', $bot->id)->where('inbound_message_id', $message->id)->first();
        }
    }

    private function nonDirect(string $address): bool
    {
        return str_contains($address, '@g.us') || str_contains($address, 'broadcast') || str_contains($address, 'status');
    }

    private function failureCode(Throwable $e): string
    {
        $text = mb_strtolower($e->getMessage());

        return str_contains($text, 'template') ? 'template_invalid' : (str_contains($text, 'subscription') || str_contains($text, 'entitlement') ? 'subscription_inactive' : 'message_creation_failed');
    }
}
