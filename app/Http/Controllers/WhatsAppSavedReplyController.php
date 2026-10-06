<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Http\Requests\StoreWhatsAppSavedReplyRequest;
use App\Models\WhatsAppSavedReply;
use App\Services\Inbox\WhatsAppSavedReplyQuery;
use App\Services\Inbox\WhatsAppSavedReplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class WhatsAppSavedReplyController extends Controller
{
    public function index(Request $r, WhatsAppSavedReplyQuery $q): JsonResponse|View
    {
        $this->authorize('viewAny', WhatsAppSavedReply::class);

        $replies = $q->paginate($r->only(['search', 'shortcut', 'status', 'per_page']));

        return $r->expectsJson() ? response()->json($replies) : view('tenant.inbox.saved-replies', compact('replies'));
    }

    public function store(StoreWhatsAppSavedReplyRequest $r, TenantContext $ctx, WhatsAppSavedReplyService $s): JsonResponse|RedirectResponse
    {
        $this->authorize('create', WhatsAppSavedReply::class);

        $reply = $s->save($ctx->get(), $r->user(), $r->validated());

        return $r->expectsJson() ? response()->json($reply, 201) : back()->with('status', 'Saved reply created.');
    }

    public function update(StoreWhatsAppSavedReplyRequest $r, WhatsAppSavedReply $savedReply, TenantContext $ctx, WhatsAppSavedReplyService $s): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $savedReply);

        $reply = $s->save($ctx->get(), $r->user(), $r->validated(), $savedReply);

        return $r->expectsJson() ? response()->json($reply) : back()->with('status', 'Saved reply updated.');
    }

    public function archive(Request $r, WhatsAppSavedReply $savedReply, TenantContext $ctx, WhatsAppSavedReplyService $s): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $savedReply);

        $reply = $s->archive($ctx->get(), $r->user(), $savedReply);

        return $r->expectsJson() ? response()->json($reply) : back()->with('status', 'Saved reply archived.');
    }

    public function restore(string $savedReply, Request $r, TenantContext $ctx, WhatsAppSavedReplyService $s): JsonResponse|RedirectResponse
    {
        $reply = WhatsAppSavedReply::withTrashed()->where('tenant_id', $ctx->id())->where('uuid', $savedReply)->firstOrFail();
        $this->authorize('update', $reply);

        $reply = $s->archive($ctx->get(), $r->user(), $reply, true);

        return $r->expectsJson() ? response()->json($reply) : back()->with('status', 'Saved reply restored.');
    }
}
