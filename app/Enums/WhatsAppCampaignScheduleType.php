<?php

namespace App\Enums;

enum WhatsAppCampaignScheduleType: string
{
    case SendNow = 'send_now';
    case Scheduled = 'scheduled';
}
