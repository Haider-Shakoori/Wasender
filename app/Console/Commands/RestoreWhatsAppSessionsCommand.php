<?php

namespace App\Console\Commands;

use App\Enums\WhatsAppSessionStatus;
use App\Jobs\RestoreWhatsAppSession;
use App\Models\WhatsAppSession;
use Illuminate\Console\Command;

final class RestoreWhatsAppSessionsCommand extends Command
{
    protected $signature = 'whatsapp-sessions:restore {--limit=200 : Maximum sessions to queue in one pass}';

    protected $description = 'Queue eligible WhatsApp sessions for connector restoration after a restart';

    public function handle(): int
    {
        $limit = max(1, min((int) $this->option('limit'), (int) config('whatsapp.recovery_batch_size', 200)));
        $staggerSeconds = max(0, (int) config('whatsapp.recovery_stagger_seconds', 2));

        $statuses = [
            WhatsAppSessionStatus::Initializing->value,
            WhatsAppSessionStatus::QrPending->value,
            WhatsAppSessionStatus::Authenticating->value,
            WhatsAppSessionStatus::Authenticated->value,
            WhatsAppSessionStatus::Ready->value,
            WhatsAppSessionStatus::Reconnecting->value,
        ];

        $sessions = WhatsAppSession::query()
            ->whereIn('status', $statuses)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [WhatsAppSessionStatus::Ready->value])
            ->orderBy('id')
            ->limit($limit)
            ->get(['id']);

        foreach ($sessions as $index => $session) {
            RestoreWhatsAppSession::dispatch($session->id)
                ->delay(now()->addSeconds($index * $staggerSeconds));
        }

        $this->info("Queued {$sessions->count()} WhatsApp session(s) for restoration.");

        return self::SUCCESS;
    }
}
