<?php

namespace App\Enums;

enum WhatsAppMessageTemplateType: string
{
    case Text = 'text';
    case Image = 'image';
    case Document = 'document';
    case Audio = 'audio';
    case Video = 'video';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
