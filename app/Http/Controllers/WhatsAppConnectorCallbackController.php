<?php

namespace App\Http\Controllers;

use App\Enums\WhatsAppSessionStatus;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppSessionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WhatsAppConnectorCallbackController extends Controller
{
    public function __invoke(Request $request, WhatsAppSessionLifecycleService $lifecycle): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'uuid'], 'reference' => ['required', 'uuid'],
            'event' => ['required', 'in:qr,authenticating,authenticated,ready,disconnected,auth_failure,heartbeat'],
            'occurred_at' => ['required', 'date'], 'qr' => ['nullable', 'string', 'max:200000'],
            'reason_code' => ['nullable', 'string', 'max:80'], 'message' => ['nullable', 'string', 'max:500'],
            'phone_number' => ['nullable', 'string', 'max:32'], 'display_name' => ['nullable', 'string', 'max:160'],
            'platform' => ['nullable', 'string', 'max:40'], 'wid' => ['nullable', 'string', 'max:100'],
        ]);
        $session = WhatsAppSession::where('uuid', $data['reference'])->firstOrFail();
        if (DB::table('whatsapp_callback_events')->where('event_uuid', $data['event_id'])->exists()) {
            return response()->json(['accepted' => true, 'duplicate' => true]);
        }
        DB::transaction(function () use ($data, $session, $lifecycle): void {
            DB::table('whatsapp_callback_events')->insert(['event_uuid' => $data['event_id'], 'whatsapp_session_id' => $session->id, 'event' => $data['event'], 'processed_at' => now()]);
            if ($data['event'] === 'qr') {
                $lifecycle->recordQr($session, (string) $data['qr']);

                return;
            }
            if ($data['event'] === 'heartbeat') {
                $session->update(['last_seen_at' => now(), 'last_health_check_at' => now()]);

                return;
            }
            $target = match ($data['event']) {
                'authenticating' => WhatsAppSessionStatus::Authenticating,
                'authenticated' => WhatsAppSessionStatus::Authenticated,
                'ready' => WhatsAppSessionStatus::Ready,
                'disconnected' => WhatsAppSessionStatus::Disconnected,
                'auth_failure' => WhatsAppSessionStatus::Failed,
            };
            if ($target === WhatsAppSessionStatus::Ready) {
                $session->update(['phone_number' => $data['phone_number'] ?? null, 'display_name' => $data['display_name'] ?? null, 'platform' => $data['platform'] ?? null, 'wid' => $data['wid'] ?? null]);
            }
            $lifecycle->transition($session->refresh(), $target, 'connector', $data['reason_code'] ?? null, $data['message'] ?? null);
        }, 3);

        return response()->json(['accepted' => true], 202);
    }
}
