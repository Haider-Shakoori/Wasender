<?php

namespace App\Jobs;

use App\Contracts\Messaging\MessagingConnector;
use App\Enums\WhatsAppSessionStatus;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppSessionLifecycleService;
use App\Services\WhatsAppSessionRuntimeEligibility;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class ManageWhatsAppSession implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $sessionId, public string $action)
    {
        $this->onQueue(config('whatsapp.queue'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("wa:manage:{$this->sessionId}"))->expireAfter(120)];
    }

    public function handle(MessagingConnector $connector, WhatsAppSessionLifecycleService $lifecycle, WhatsAppSessionRuntimeEligibility $eligibility): void
    {
        $session = WhatsAppSession::withTrashed()->with(['tenant.currentSubscription'])->findOrFail($this->sessionId);

        if ($this->action === 'reconnect' && ! $eligibility->eligible($session)) {
            if ($session->status !== WhatsAppSessionStatus::Disconnected) {
                $lifecycle->transition($session, WhatsAppSessionStatus::Disconnected, 'system', 'subscription_inactive', 'Session paused because the tenant subscription does not permit runtime access.');
            }

            return;
        }

        match ($this->action) {
            'reconnect' => $connector->restart($session->uuid),
            'disconnect' => $connector->disconnect($session->uuid),
            'delete' => $connector->delete($session->uuid),
        };
        if ($this->action === 'disconnect') {
            $lifecycle->transition($session, WhatsAppSessionStatus::Disconnected, 'user', 'manual_disconnect', 'Disconnected by a workspace user.');
        }
        if ($this->action === 'delete') {
            $deleted = $lifecycle->transition($session, WhatsAppSessionStatus::Deleted, 'laravel');
            $deleted->delete();
        }
    }
}
