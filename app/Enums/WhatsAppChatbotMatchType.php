<?php

namespace App\Enums;

enum WhatsAppChatbotMatchType: string
{
    case Any = 'any';
    case Exact = 'exact';
    case Contains = 'contains';
    case StartsWith = 'starts_with';
    case Condition = 'condition';
}
