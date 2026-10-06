<?php

namespace App\Jobs;

use App\Contracts\Messaging\MessagingConnector;
use App\Models\WhatsAppSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class RestoreWhatsAppSession implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $sessionId)
    {
        $this->onQueue(config('whatsapp.queue'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("wa:restore:{$this->sessionId}"))
                ->releaseAfter(15)
                ->expireAfter(180),
        ];
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(MessagingConnector $connector): void
    {
        $session = WhatsAppSession::query()
            ->with('tenant:id,uuid')
            ->findOrFail($this->sessionId);

        $connector->createSession([
            'reference' => $session->uuid,
            'storage_key' => $session->storage_key,
            'tenant_uuid' => $session->tenant->uuid,
        ]);
    }
}
