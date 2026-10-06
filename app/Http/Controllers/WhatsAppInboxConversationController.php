<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Enums\MembershipStatus;
use App\Models\TenantMembership;
use App\Models\WhatsAppConversationLabel;
use App\Models\WhatsAppConversationNote;
use App\Models\WhatsAppMessageTemplate;
use App\Services\Inbox\WhatsAppConversationActivityQuery;
use App\Services\Inbox\WhatsAppInboxConversationQuery;
use App\Services\Inbox\WhatsAppInboxMessageQuery;
use App\Services\Inbox\WhatsAppSavedReplyQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class WhatsAppInboxConversationController extends Controller
{
    public function index(Request $request, WhatsAppInboxConversationQuery $query): JsonResponse|View
    {
        $conversations = $query->paginate($request->only(['search', 'status', 'session_uuid', 'unread', 'contact', 'sort', 'per_page', 'assigned_user_uuid', 'assignment', 'priority', 'label_uuid']));

        return $request->expectsJson() ? response()->json($conversations) : view('tenant.inbox.index', compact('conversations'));
    }

    public function show(string $conversation, Request $request, WhatsAppInboxConversationQuery $query, WhatsAppInboxMessageQuery $messages, WhatsAppConversationActivityQuery $activities, WhatsAppSavedReplyQuery $replies, TenantContext $context): JsonResponse|View
    {
        $conversation = $query->find($conversation);
        $conversation->load(['chatbotStates' => fn ($query) => $query->with(['chatbot:id,uuid,name', 'lastRule:id,uuid,name'])->latest('updated_at')]);
        if ($request->expectsJson()) {
            return response()->json($conversation);
        }
        $messagePage = $messages->paginate($conversation, ['before' => $request->input('before'), 'per_page' => 40]);
        $notes = WhatsAppConversationNote::where('tenant_id', $context->id())->where('whatsapp_conversation_id', $conversation->id)->with(['author:id,uuid,name', 'mentions:id,uuid,name'])->latest()->limit(30)->get();
        $activityPage = $activities->paginate($conversation, 30);
        $savedReplies = $replies->paginate(['status' => 'active', 'per_page' => 100]);
        $members = TenantMembership::forTenant($context->id())->where('status', MembershipStatus::Active)->with('user:id,uuid,name')->get()->pluck('user')->filter();
        $labels = WhatsAppConversationLabel::forTenant($context->id())->orderBy('name')->get();
        $templates = WhatsAppMessageTemplate::forTenant($context->id())->where('status', 'published')->whereNotNull('current_published_version_id')->with('currentPublishedVersion:id,whatsapp_message_template_id,version_number')->orderBy('name')->limit(100)->get();

        return view('tenant.inbox.show', compact('conversation', 'messagePage', 'notes', 'activityPage', 'savedReplies', 'members', 'labels', 'templates'));
    }
}
