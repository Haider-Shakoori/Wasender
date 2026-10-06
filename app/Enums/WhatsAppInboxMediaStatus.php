<?php

namespace App\Enums;

enum WhatsAppInboxMediaStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Available = 'available';
    case Failed = 'failed';
}
