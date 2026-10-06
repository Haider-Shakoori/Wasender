<?php

namespace App\Jobs;

use App\Contracts\TenantContext;
use App\Models\WhatsAppInboxMessage;
use App\Services\Chatbots\ProcessWhatsAppChatbotMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessWhatsAppChatbotMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $messageId)
    {
        $this->onQueue(config('chatbots.queue'));
    }

    public function handle(TenantContext $context, ProcessWhatsAppChatbotMessageService $processor): void
    {
        $message = WhatsAppInboxMessage::with('tenant')->find($this->messageId);
        if (! $message) {
            return;
        } $context->set($message->tenant);
        $processor->process($this->messageId);
    }
}
