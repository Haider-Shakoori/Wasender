<?php

namespace App\Events;

use App\Models\WhatsAppInboxMessage;
use Illuminate\Foundation\Events\Dispatchable;

final class InboxMessageReceived
{
    use Dispatchable;

    public function __construct(public WhatsAppInboxMessage $message) {}
}
