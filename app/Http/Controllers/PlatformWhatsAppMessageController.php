<?php

namespace App\Http\Controllers;

use App\Jobs\ReconcileWhatsAppMessage;
use App\Models\WhatsAppMessage;
use App\Services\PlatformAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformWhatsAppMessageController extends Controller
{
    public function index(Request $request): View
    {
        $messages = WhatsAppMessage::with(['tenant:id,uuid,name', 'session:id,uuid,name'])->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()->paginate(25)->withQueryString();

        return view('platform.messages.index', compact('messages'));
    }

    public function show(WhatsAppMessage $message): View
    {
        $message->load(['tenant:id,uuid,name', 'session:id,uuid,name', 'events' => fn ($q) => $q->latest('occurred_at')->limit(50), 'attempts']);

        return view('platform.messages.show', compact('message'));
    }

    public function reconcile(Request $request, WhatsAppMessage $message, PlatformAuditService $audit): RedirectResponse
    {
        abort_unless($message->failure_code === 'ambiguous_transport', 422);
        ReconcileWhatsAppMessage::dispatch($message->id);
        $audit->record('whatsapp.message_reconciliation_requested', $request->user(), $message, ['message_uuid' => $message->uuid]);

        return back()->with('status', 'Safe reconciliation queued. No resend will occur.');
    }
}
