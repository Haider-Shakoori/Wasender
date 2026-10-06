<?php

namespace App\Enums;

enum WhatsAppInboxMessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
