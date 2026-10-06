<?php

namespace App\Enums;

enum WhatsAppChatbotActionType: string
{
    case ReplyText = 'reply_text';
    case ReplyTemplate = 'reply_template';
    case Handoff = 'handoff_to_human';
    case Stop = 'stop_bot';
}
