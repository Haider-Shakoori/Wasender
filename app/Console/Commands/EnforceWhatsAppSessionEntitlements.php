<?php

namespace App\Console\Commands;

use App\Enums\WhatsAppSessionStatus;
use App\Jobs\ManageWhatsAppSession;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppSessionRuntimeEligibility;
use Illuminate\Console\Command;

final class EnforceWhatsAppSessionEntitlements extends Command
{
    protected $signature = 'whatsapp-sessions:enforce-entitlements {--limit=500 : Maximum active sessions to inspect}';

    protected $description = 'Pause WhatsApp runtimes for suspended tenants or subscriptions without access';

    public function handle(WhatsAppSessionRuntimeEligibility $eligibility): int
    {
        $limit = max(1, min((int) $this->option('limit'), 5000));

        $statuses = [
            WhatsAppSessionStatus::Creating,
            WhatsAppSessionStatus::Initializing,
            WhatsAppSessionStatus::QrPending,
            WhatsAppSessionStatus::Authenticating,
            WhatsAppSessionStatus::Authenticated,
            WhatsAppSessionStatus::Ready,
            WhatsAppSessionStatus::Reconnecting,
        ];

        $sessions = WhatsAppSession::query()
            ->with(['tenant.currentSubscription'])
            ->whereIn('status', array_map(fn (WhatsAppSessionStatus $status) => $status->value, $statuses))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $paused = 0;

        foreach ($sessions as $session) {
            if ($eligibility->eligible($session)) {
                continue;
            }

            ManageWhatsAppSession::dispatch($session->id, 'disconnect');
            $paused++;
        }

        $this->info("Queued {$paused} WhatsApp session(s) for safe pause.");

        return self::SUCCESS;
    }
}
