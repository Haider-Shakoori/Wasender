<?php

namespace App\Events;

use App\Models\WhatsAppConversation;
use Illuminate\Foundation\Events\Dispatchable;

final class InboxConversationCreated
{
    use Dispatchable;

    public function __construct(public WhatsAppConversation $conversation) {}
}
