<?php

namespace App\Http\Controllers;

use App\Enums\WhatsAppConversationPriority;
use App\Enums\WhatsAppConversationStatus;
use App\Http\Requests\AssignWhatsAppConversationRequest;
use App\Http\Requests\ChangeWhatsAppConversationRequest;
use App\Models\WhatsAppConversation;
use App\Services\Inbox\WhatsAppConversationOperations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class WhatsAppConversationOperationController extends Controller
{
    public function assign(AssignWhatsAppConversationRequest $r, WhatsAppConversation $conversation, WhatsAppConversationOperations $s): JsonResponse|RedirectResponse
    {
        $this->authorize('assign', $conversation);

        $result = $s->assign($conversation, $r->user(), $r->validated('user_uuid'));

        return $r->expectsJson() ? response()->json($result) : back()->with('status', 'Assignment updated.');
    }

    public function status(ChangeWhatsAppConversationRequest $r, WhatsAppConversation $conversation, WhatsAppConversationOperations $s): JsonResponse|RedirectResponse
    {
        $this->authorize('changeStatus', $conversation);

        $result = $s->status($conversation, $r->user(), WhatsAppConversationStatus::from($r->validated('status')));

        return $r->expectsJson() ? response()->json($result) : back()->with('status', 'Conversation status updated.');
    }

    public function priority(ChangeWhatsAppConversationRequest $r, WhatsAppConversation $conversation, WhatsAppConversationOperations $s): JsonResponse|RedirectResponse
    {
        $this->authorize('changePriority', $conversation);

        $result = $s->priority($conversation, $r->user(), WhatsAppConversationPriority::from($r->validated('priority')));

        return $r->expectsJson() ? response()->json($result) : back()->with('status', 'Conversation priority updated.');
    }

    public function read(Request $r, WhatsAppConversation $conversation, WhatsAppConversationOperations $s): JsonResponse|RedirectResponse
    {
        $this->authorize('markRead', $conversation);

        $result = $s->read($conversation, $r->user(), true);

        return $r->expectsJson() ? response()->json($result) : back()->with('status', 'Conversation marked read.');
    }

    public function unread(Request $r, WhatsAppConversation $conversation, WhatsAppConversationOperations $s): JsonResponse|RedirectResponse
    {
        $this->authorize('markRead', $conversation);

        $result = $s->read($conversation, $r->user(), false);

        return $r->expectsJson() ? response()->json($result) : back()->with('status', 'Conversation marked unread.');
    }
}
