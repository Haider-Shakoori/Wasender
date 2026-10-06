<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppInboxMessage;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformWhatsAppInboxController extends Controller
{
    public function index(Request $request): View
    {
        $conversations = WhatsAppConversation::query()->with(['tenant:id,uuid,name', 'session:id,uuid,name', 'contact:id,uuid,display_name', 'assignedUser:id,uuid,name'])->withCount(['messages as failure_count' => fn ($q) => $q->where('status', 'failed')])
            ->when($request->tenant_uuid, fn ($q, $v) => $q->whereHas('tenant', fn ($q) => $q->where('uuid', $v)))->when($request->status, fn ($q, $v) => $q->where('status', $v))->when($request->priority, fn ($q, $v) => $q->where('priority', $v))->when($request->boolean('unread'), fn ($q) => $q->where('unread_count', '>', 0))->when($request->boolean('failures'), fn ($q) => $q->whereHas('messages', fn ($q) => $q->where('status', 'failed')))->latest('last_message_at')->paginate(30)->withQueryString();

        return view('platform.inbox.index', compact('conversations'));
    }

    public function show(WhatsAppConversation $conversation): View
    {
        $conversation->load(['tenant:id,uuid,name', 'session:id,uuid,name,status', 'contact:id,uuid,display_name', 'assignedUser:id,uuid,name', 'labels:id,uuid,name,color']);
        $messages = $conversation->messages()->select(['id', 'uuid', 'whatsapp_conversation_id', 'direction', 'message_type', 'status', 'message_preview', 'failure_code', 'occurred_at'])->latest('occurred_at')->limit(50)->get()->reverse();
        $activities = $conversation->activities()->with('actor:id,uuid,name')->latest('occurred_at')->limit(50)->get();

        return view('platform.inbox.show', compact('conversation', 'messages', 'activities'));
    }

    public function messages(Request $request): View
    {
        $messages = WhatsAppInboxMessage::query()->with(['tenant:id,uuid,name', 'conversation:id,uuid', 'session:id,uuid,name'])->when($request->direction, fn ($q, $v) => $q->where('direction', $v))->when($request->status, fn ($q, $v) => $q->where('status', $v))->latest('occurred_at')->paginate(40)->withQueryString();

        return view('platform.inbox.messages', compact('messages'));
    }

    public function failures(Request $request): View
    {
        $messages = WhatsAppInboxMessage::query()->where('status', 'failed')->with(['tenant:id,uuid,name', 'conversation:id,uuid', 'session:id,uuid,name', 'outboundMessage:id,uuid,failure_retryable,failure_code,status'])->when($request->failure_code, fn ($q, $v) => $q->where('failure_code', $v))->latest('failed_at')->paginate(40)->withQueryString();

        return view('platform.inbox.failures', compact('messages'));
    }
}
