<?php

namespace App\Enums;

enum WhatsAppConversationPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
