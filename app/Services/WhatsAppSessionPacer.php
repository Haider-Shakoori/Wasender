<?php

namespace App\Services;

use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\DB;

final class WhatsAppSessionPacer
{
    public function reserve(WhatsAppSession $session): int
    {
        return DB::transaction(function () use ($session): int {
            $locked = WhatsAppSession::query()->lockForUpdate()->findOrFail($session->id);
            $now = now();

            if ($locked->next_send_at?->greaterThan($now)) {
                return max(1, (int) ceil($now->diffInMilliseconds($locked->next_send_at, false)));
            }

            $min = max(0, (int) config('whatsapp_messages.send_delay_min_ms', 5000));
            $max = max($min, (int) config('whatsapp_messages.send_delay_max_ms', 7000));
            $delayMs = random_int($min, $max);

            $locked->forceFill([
                'last_dispatch_reserved_at' => $now,
                'next_send_at' => $now->copy()->addMilliseconds($delayMs),
            ])->save();

            return 0;
        }, 3);
    }
}
