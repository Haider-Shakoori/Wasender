<?php

namespace App\Enums;

enum SubscriptionSource: string
{
    case Trial = 'trial';
    case Manual = 'manual';
    case Promotion = 'promotion';
    case Provider = 'provider';
    case Migration = 'migration';
}
