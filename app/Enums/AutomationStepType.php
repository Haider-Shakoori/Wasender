<?php

namespace App\Enums;

enum AutomationStepType: string
{
    case Action = 'action';
    case Condition = 'condition';
    case Delay = 'delay';
    case Branch = 'branch';
    case Stop = 'stop';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
