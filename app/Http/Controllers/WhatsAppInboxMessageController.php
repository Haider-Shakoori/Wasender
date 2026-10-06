<?php

namespace App\Http\Controllers;

use App\Services\Inbox\WhatsAppInboxConversationQuery;
use App\Services\Inbox\WhatsAppInboxMessageQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WhatsAppInboxMessageController extends Controller
{
    public function index(string $conversation, Request $request, WhatsAppInboxConversationQuery $conversations, WhatsAppInboxMessageQuery $messages): JsonResponse
    {
        return response()->json($messages->paginate($conversations->find($conversation), $request->only(['direction', 'message_type', 'before', 'after', 'per_page'])));
    }
}
