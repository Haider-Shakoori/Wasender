<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppSessionStatus;
use App\Http\Requests\StoreWhatsAppMessageRequest;
use App\Jobs\DispatchWhatsAppMessage;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppSession;
use App\Services\AuditService;
use App\Services\WhatsAppMessageLifecycleService;
use App\Services\WhatsAppMessageQuery;
use App\Services\WhatsAppMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class WhatsAppMessageController extends Controller
{
    public function index(Request $request, WhatsAppMessageQuery $query): View
    {
        return view('tenant.messages.index', ['messages' => $query->paginate($request->only('status', 'search'))]);
    }

    public function create(TenantContext $context): View
    {
        return view('tenant.messages.create', ['sessions' => WhatsAppSession::forTenant($context->id())->where('status', WhatsAppSessionStatus::Ready)->orderBy('name')->get(), 'messageTemplates' => WhatsAppMessageTemplate::forTenant($context->id())->where('status', 'published')->whereNotNull('current_published_version_id')->with('currentPublishedVersion:id,whatsapp_message_template_id,version_number')->orderBy('name')->limit(100)->get(['id', 'uuid', 'name', 'type', 'current_published_version_id'])]);
    }

    public function store(StoreWhatsAppMessageRequest $request, TenantContext $context, WhatsAppMessageService $service): RedirectResponse
    {
        $message = $service->create($context->get(), $request->user(), $request->validated(), $request->file('attachment'));

        return redirect()->route('tenant.messages.show', $message)->with('status', 'Message queued for delivery.');
    }

    public function show(string $messageUuid, WhatsAppMessageQuery $query): View
    {
        return view('tenant.messages.show', ['message' => $query->find($messageUuid)]);
    }

    public function status(string $messageUuid, WhatsAppMessageQuery $query): JsonResponse
    {
        $message = $query->find($messageUuid);

        return response()->json(['status' => $message->status->value, 'label' => $message->status->label(), 'terminal' => $message->status->isTerminal(),
            'can_cancel' => $message->status->canCancel(), 'can_retry' => $message->status === WhatsAppMessageStatus::Failed && $message->failure_retryable && $message->attempts < $message->max_attempts,
            'updated_at' => $message->updated_at->toIso8601String(), 'next_poll_after_ms' => $message->status->isTerminal() ? null : 3000]);
    }

    public function cancel(Request $request, string $messageUuid, WhatsAppMessageQuery $query, WhatsAppMessageLifecycleService $lifecycle, AuditService $audit): RedirectResponse
    {
        $message = $query->find($messageUuid);
        if ($message->status->canCancel()) {
            $lifecycle->transition($message, WhatsAppMessageStatus::Cancelled, 'user');
        }
        $audit->recordDomain('whatsapp.message_cancelled', $request->user(), $message->tenant, $message);

        return back()->with('status', 'Cancellation recorded.');
    }

    public function retry(Request $request, string $messageUuid, WhatsAppMessageQuery $query, WhatsAppMessageLifecycleService $lifecycle, AuditService $audit): RedirectResponse
    {
        $message = $query->find($messageUuid);
        abort_unless($message->status === WhatsAppMessageStatus::Failed && $message->failure_retryable && $message->attempts < $message->max_attempts, 422);
        $message = $lifecycle->transition($message, WhatsAppMessageStatus::Queued, 'user', ['failure_code' => null, 'failure_message' => null, 'failure_retryable' => false]);
        DispatchWhatsAppMessage::dispatch($message->id);
        $audit->recordDomain('whatsapp.message_retry_requested', $request->user(), $message->tenant, $message);

        return back()->with('status', 'Retry queued.');
    }
}
