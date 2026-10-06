<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Models\WhatsAppChatbot;
use App\Models\WhatsAppConversation;
use App\Services\Chatbots\ChangeChatbotConversationStateService;
use Illuminate\Http\RedirectResponse;

final class WhatsAppChatbotConversationStateController extends Controller
{
    public function __invoke(WhatsAppConversation $conversation, string $state, TenantContext $context, ChangeChatbotConversationStateService $service): RedirectResponse
    {
        abort_unless($conversation->tenant_id === $context->id() && in_array($state, ['stopped', 'active'], true), 404);
        $bot = WhatsAppChatbot::forTenant($context->id())->whereHas('sessions', fn ($q) => $q->where('whatsapp_sessions.id', $conversation->whatsapp_session_id))->firstOrFail();
        $service->change($conversation, $bot, $state, auth()->user());

        return back()->with('status', $state === 'active' ? 'Chatbot resumed.' : 'Chatbot paused.');
    }
}
