<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Enums\WhatsAppSessionStatus;
use App\Jobs\ManageWhatsAppSession;
use App\Services\AuditService;
use App\Services\WhatsAppSessionLifecycleService;
use App\Services\WhatsAppSessionQuery;
use App\Services\WhatsAppSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class WhatsAppSessionController extends Controller
{
    public function index(WhatsAppSessionQuery $query): View
    {
        return view('tenant.whatsapp.index', ['sessions' => $query->paginate(), 'summary' => $query->summary()]);
    }

    public function store(Request $request, TenantContext $context, WhatsAppSessionService $service): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:100']]);
        $session = $service->create($context->get(), $request->user(), $data['name']);

        return redirect()->route('tenant.whatsapp.show', $session)->with('status', 'Session creation started.');
    }

    public function show(string $session, WhatsAppSessionQuery $query): View
    {
        return view('tenant.whatsapp.show', ['session' => $query->find($session)]);
    }

    public function status(string $session, WhatsAppSessionQuery $query, WhatsAppSessionLifecycleService $lifecycle): JsonResponse
    {
        $model = $query->find($session);
        $qrAvailable = $model->status === WhatsAppSessionStatus::QrPending && $lifecycle->qr($model) !== null;

        return response()->json([
            'status' => $model->status->value,
            'status_label' => $model->status->label(),
            'qr_available' => $qrAvailable,
            'qr_generation_count' => $qrAvailable ? $model->qr_generation_count : null,
            'phone_number' => $model->phone_number,
            'display_name' => $model->display_name,
            'last_seen_at' => $model->last_seen_at?->toIso8601String(),
            'ready_at' => $model->ready_at?->toIso8601String(),
            'reconnect_attempts' => $model->reconnect_attempts,
            'failure_code' => $model->failure_code,
            'failure_message' => $model->failure_message,
            'last_updated_at' => $model->updated_at->toIso8601String(),
            'next_poll_after_ms' => $model->status->isTransitional() ? 3000 : ($model->status === WhatsAppSessionStatus::Ready ? 10000 : null),
        ]);
    }

    public function qr(string $session, WhatsAppSessionQuery $query, WhatsAppSessionLifecycleService $lifecycle): Response
    {
        $model = $query->find($session);
        abort_unless($model->status === WhatsAppSessionStatus::QrPending, 404);
        $dataUrl = $lifecycle->qr($model);
        abort_unless(is_string($dataUrl) && str_starts_with($dataUrl, 'data:image/png;base64,'), 404);
        $binary = base64_decode(substr($dataUrl, 22), true);
        abort_unless(is_string($binary) && strlen($binary) <= 200000, 404);

        return response($binary)->header('Content-Type', 'image/png')->header('Cache-Control', 'no-store, private, max-age=0')->header('Pragma', 'no-cache');
    }

    public function reconnect(Request $request, string $session, WhatsAppSessionQuery $query, WhatsAppSessionLifecycleService $lifecycle, AuditService $audit): RedirectResponse
    {
        $model = $query->find($session);
        $target = $model->status === WhatsAppSessionStatus::Failed
            ? WhatsAppSessionStatus::Initializing
            : WhatsAppSessionStatus::Reconnecting;
        $lifecycle->transition($model, $target, 'user');
        $model->increment('reconnect_attempts');
        $model->update(['last_reconnect_attempt_at' => now(), 'updated_by' => $request->user()->id]);
        ManageWhatsAppSession::dispatch($model->id, 'reconnect');
        $audit->recordDomain('whatsapp.session_reconnect_requested', $request->user(), $model->tenant, $model);

        return back()->with('status', 'Reconnect requested.');
    }

    public function disconnect(Request $request, string $session, WhatsAppSessionQuery $query, AuditService $audit): RedirectResponse
    {
        $model = $query->find($session);
        ManageWhatsAppSession::dispatch($model->id, 'disconnect');
        $audit->recordDomain('whatsapp.session_disconnect_requested', $request->user(), $model->tenant, $model);

        return back()->with('status', 'Disconnect requested.');
    }

    public function destroy(Request $request, string $session, WhatsAppSessionQuery $query, WhatsAppSessionLifecycleService $lifecycle, AuditService $audit): RedirectResponse
    {
        $model = $query->find($session);
        $lifecycle->transition($model, WhatsAppSessionStatus::Deleting, 'user');
        ManageWhatsAppSession::dispatch($model->id, 'delete');
        $audit->recordDomain('whatsapp.session_delete_requested', $request->user(), $model->tenant, $model);

        return redirect()->route('tenant.whatsapp.index')->with('status', 'Session deletion started.');
    }
}
