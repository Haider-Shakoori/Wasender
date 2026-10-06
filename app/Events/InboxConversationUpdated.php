<?php

namespace App\Events;

use App\Models\WhatsAppConversation;
use Illuminate\Foundation\Events\Dispatchable;

final class InboxConversationUpdated
{
    use Dispatchable;

    public function __construct(public WhatsAppConversation $conversation) {}
}
