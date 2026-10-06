<?php

namespace App\Services;

use App\Enums\WhatsAppSessionStatus as Status;
use App\Models\WhatsAppSession;
use App\Models\WhatsAppSessionEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WhatsAppSessionLifecycleService
{
    private const ALLOWED = [
        'creating' => ['initializing', 'disconnected', 'failed'],
        'initializing' => ['qr_pending', 'authenticated', 'ready', 'disconnected', 'failed'],
        'qr_pending' => ['authenticating', 'failed', 'disconnected'],
        'authenticating' => ['authenticated', 'ready', 'failed', 'disconnected'],
        'authenticated' => ['ready', 'failed', 'disconnected'],
        'ready' => ['reconnecting', 'disconnected', 'failed', 'deleting'],
        'reconnecting' => ['ready', 'qr_pending', 'disconnected', 'failed'],
        'disconnected' => ['initializing', 'reconnecting', 'qr_pending', 'deleting'],
        'failed' => ['initializing', 'deleting'],
        'deleting' => ['deleted'],
    ];

    public function transition(
        WhatsAppSession $session,
        Status $to,
        string $source,
        ?string $reasonCode = null,
        ?string $message = null,
        array $metadata = [],
    ): WhatsAppSession {
        return DB::transaction(function () use ($session, $to, $source, $reasonCode, $message, $metadata): WhatsAppSession {
            $locked = WhatsAppSession::withTrashed()->lockForUpdate()->findOrFail($session->id);
            $from = $locked->status;
            if ($from === $to) {
                return $locked;
            }
            if (! in_array($to->value, self::ALLOWED[$from->value] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "The {$from->value} session cannot transition to {$to->value}."]);
            }

            $changes = ['status' => $to];
            if (in_array($to, [Status::Initializing, Status::QrPending, Status::Authenticating, Status::Authenticated, Status::Ready], true)) {
                $changes += ['failure_code' => null, 'failure_message' => null];
            }
            if ($to === Status::Authenticated) {
                $changes['authenticated_at'] = now();
            }
            if ($to === Status::Ready) {
                $changes += ['ready_at' => now(), 'last_seen_at' => now(), 'failure_code' => null, 'failure_message' => null, 'reconnect_attempts' => 0];
            }
            if ($to === Status::Disconnected) {
                $changes += ['disconnected_at' => now(), 'disconnect_reason' => str($message)->limit(500)];
            }
            if ($to === Status::Failed) {
                $changes += ['failure_code' => str($reasonCode)->limit(80), 'failure_message' => str($message)->limit(500)];
            }
            $locked->update($changes);
            $this->record($locked, 'status.changed', $source, $from, $to, $reasonCode, $message, $metadata);

            if (in_array($to, [Status::Authenticated, Status::Ready, Status::Disconnected, Status::Failed, Status::Deleting, Status::Deleted], true)) {
                $this->forgetQr($locked);
            }

            return $locked->refresh();
        }, 3);
    }

    public function recordQr(WhatsAppSession $session, string $qr): void
    {
        if (strlen($qr) > 200000 || strlen($qr) < 32 || ! str_starts_with($qr, 'data:image/png;base64,')) {
            throw ValidationException::withMessages(['qr' => 'The connector supplied an invalid QR payload.']);
        }
        Cache::put($this->qrKey($session), encrypt($qr), now()->addSeconds(config('whatsapp.qr_ttl_seconds')));
        $session->increment('qr_generation_count');
        $session->update(['last_qr_generated_at' => now(), 'failure_code' => null, 'failure_message' => null]);
        if ($session->status !== Status::QrPending) {
            $this->transition($session, Status::QrPending, 'connector');
        }
        WhatsAppSessionEvent::create([
            'tenant_id' => $session->tenant_id,
            'whatsapp_session_id' => $session->id,
            'event' => 'qr.generated',
            'source' => 'connector',
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function qr(WhatsAppSession $session): ?string
    {
        $encrypted = Cache::get($this->qrKey($session));

        return is_string($encrypted) ? decrypt($encrypted) : null;
    }

    public function forgetQr(WhatsAppSession $session): void
    {
        Cache::forget($this->qrKey($session));
    }

    private function record(WhatsAppSession $session, string $event, string $source, Status $from, Status $to, ?string $reasonCode, ?string $message, array $metadata): void
    {
        WhatsAppSessionEvent::create([
            'tenant_id' => $session->tenant_id,
            'whatsapp_session_id' => $session->id,
            'event' => $event,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'source' => $source,
            'reason_code' => str($reasonCode)->limit(80),
            'message' => str($message)->limit(500),
            'metadata' => collect($metadata)->except(['qr', 'token', 'secret', 'cookie', 'path'])->all(),
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function qrKey(WhatsAppSession $session): string
    {
        return "wa:tenant:{$session->tenant_id}:session:{$session->uuid}:qr";
    }
}
