<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignWhatsAppConversationLabelsRequest;
use App\Models\WhatsAppConversation;
use App\Services\Inbox\WhatsAppConversationLabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class WhatsAppConversationLabelController extends Controller
{
    public function __invoke(AssignWhatsAppConversationLabelsRequest $r, WhatsAppConversation $conversation, WhatsAppConversationLabelService $s): JsonResponse|RedirectResponse
    {
        $this->authorize('manageLabels', $conversation);

        $result = $s->sync($conversation, $r->user(), $r->validated('labels', []));

        return $r->expectsJson() ? response()->json($result) : back()->with('status', 'Conversation labels updated.');
    }
}
