<?php

namespace App\Console\Commands;

use App\Enums\WhatsAppSessionStatus;
use App\Jobs\ManageWhatsAppSession;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppSessionLifecycleService;
use Illuminate\Console\Command;

final class MonitorWhatsAppSessions extends Command
{
    protected $signature = 'whatsapp-sessions:health';

    protected $description = 'Mark stale WhatsApp sessions and queue bounded reconnect attempts';

    public function handle(WhatsAppSessionLifecycleService $lifecycle): int
    {
        $cutoff = now()->subSeconds(config('whatsapp.stale_after_seconds'));
        WhatsAppSession::query()->where('status', WhatsAppSessionStatus::Ready)
            ->where(fn ($query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $cutoff))
            ->where('reconnect_attempts', '<', config('whatsapp.max_reconnect_attempts'))
            ->eachById(function (WhatsAppSession $session) use ($lifecycle): void {
                $lifecycle->transition($session, WhatsAppSessionStatus::Reconnecting, 'health_monitor', 'heartbeat_stale', 'Connector heartbeat became stale.');
                $session->increment('reconnect_attempts');
                $session->update(['last_reconnect_attempt_at' => now(), 'last_health_check_at' => now()]);
                ManageWhatsAppSession::dispatch($session->id, 'reconnect');
            });

        return self::SUCCESS;
    }
}
