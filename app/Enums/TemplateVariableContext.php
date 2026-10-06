<?php

namespace App\Enums;

enum TemplateVariableContext: string
{
    case Generic = 'generic';
    case Contact = 'contact';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
