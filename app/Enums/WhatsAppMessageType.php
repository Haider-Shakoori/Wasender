<?php

namespace App\Enums;

enum WhatsAppMessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Document = 'document';
    case Audio = 'audio';
    case Video = 'video';
}
