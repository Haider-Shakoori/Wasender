<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendWhatsAppInboxReplyRequest;
use App\Models\WhatsAppConversation;
use App\Services\Inbox\SendWhatsAppInboxReplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class WhatsAppInboxReplyController extends Controller
{
    public function __invoke(SendWhatsAppInboxReplyRequest $request, WhatsAppConversation $conversation, SendWhatsAppInboxReplyService $service): JsonResponse|RedirectResponse
    {
        $this->authorize('reply', $conversation);
        $message = $service->send($conversation, $request->user(), $request->validated(), $request->file('attachment'));

        return $request->expectsJson() ? response()->json(['uuid' => $message->uuid, 'status' => $message->status->value], 202) : back()->with('status', 'Reply queued for delivery.');
    }
}
