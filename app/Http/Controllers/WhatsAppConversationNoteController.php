<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Http\Requests\StoreWhatsAppConversationNoteRequest;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppConversationNote;
use App\Services\Inbox\WhatsAppConversationNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class WhatsAppConversationNoteController extends Controller
{
    public function index(WhatsAppConversation $conversation, Request $r, TenantContext $ctx): JsonResponse
    {
        $this->authorize('view', $conversation);
        $notes = WhatsAppConversationNote::where('tenant_id', $ctx->id())->where('whatsapp_conversation_id', $conversation->id)->with(['author:id,uuid,name', 'mentions:id,uuid,name'])->latest()->paginate(min(100, max(1, (int) $r->input('per_page', 25))));

        return response()->json($notes);
    }

    public function store(StoreWhatsAppConversationNoteRequest $r, WhatsAppConversation $conversation, WhatsAppConversationNoteService $s): JsonResponse|RedirectResponse
    {
        $this->authorize('addNote', $conversation);

        $note = $s->create($conversation, $r->user(), $r->validated('body'), $r->validated('mention_user_uuids', []));

        return $r->expectsJson() ? response()->json($note, 201) : back()->with('status', 'Internal note added.');
    }

    public function update(StoreWhatsAppConversationNoteRequest $r, WhatsAppConversationNote $note, WhatsAppConversationNoteService $s): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $note);

        $result = $s->update($note, $r->user(), $r->validated('body'), $r->validated('mention_user_uuids', []));

        return $r->expectsJson() ? response()->json($result) : back()->with('status', 'Internal note updated.');
    }

    public function destroy(Request $r, WhatsAppConversationNote $note, WhatsAppConversationNoteService $s): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $note);
        $s->delete($note, $r->user());

        return $r->expectsJson() ? response()->json(status: 204) : back()->with('status', 'Internal note deleted.');
    }
}
