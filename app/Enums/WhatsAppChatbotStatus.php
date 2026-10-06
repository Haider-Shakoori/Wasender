<?php

namespace App\Enums;

enum WhatsAppChatbotStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
