<?php

namespace App\Listeners;

use App\Events\InboxMessageReceived;
use App\Jobs\ProcessWhatsAppChatbotMessage;

final class QueueWhatsAppChatbotProcessing
{
    public function handle(InboxMessageReceived $event): void
    {
        ProcessWhatsAppChatbotMessage::dispatch($event->message->id)->afterCommit();
    }
}
