<?php

namespace App\Enums;

enum WhatsAppConversationStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Closed = 'closed';
    case Archived = 'archived';
}
