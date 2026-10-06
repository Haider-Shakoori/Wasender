<?php

namespace App\Jobs;

use App\Contracts\Messaging\MessagingConnector;
use App\Enums\WhatsAppSessionStatus;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppSessionLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

final class InitializeWhatsAppSession implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $sessionId)
    {
        $this->onQueue(config('whatsapp.queue'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("wa:init:{$this->sessionId}"))->expireAfter(120)];
    }

    public function handle(MessagingConnector $connector, WhatsAppSessionLifecycleService $lifecycle): void
    {
        $session = WhatsAppSession::findOrFail($this->sessionId);
        $lifecycle->transition($session, WhatsAppSessionStatus::Initializing, 'laravel');
        try {
            $connector->createSession(['reference' => $session->uuid, 'storage_key' => $session->storage_key, 'tenant_uuid' => $session->tenant->uuid]);
        } catch (Throwable $exception) {
            $lifecycle->transition($session->refresh(), WhatsAppSessionStatus::Failed, 'laravel', 'connector_unavailable', $exception->getMessage());
            throw $exception;
        }
    }
}
