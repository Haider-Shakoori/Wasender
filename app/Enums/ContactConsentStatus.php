<?php

namespace App\Enums;

enum ContactConsentStatus: string
{
    case Unknown = 'unknown';
    case Pending = 'pending';
    case Granted = 'granted';
    case Denied = 'denied';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
}
