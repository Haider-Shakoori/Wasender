<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppConversation;
use App\Services\Inbox\WhatsAppConversationActivityQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WhatsAppConversationActivityController extends Controller
{
    public function __invoke(WhatsAppConversation $conversation, Request $r, WhatsAppConversationActivityQuery $q): JsonResponse
    {
        $this->authorize('viewActivity', $conversation);

        return response()->json($q->paginate($conversation, (int) $r->input('per_page', 50)));
    }
}
