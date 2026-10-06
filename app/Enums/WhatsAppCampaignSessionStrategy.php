<?php

namespace App\Enums;

enum WhatsAppCampaignSessionStrategy: string
{
    case Single = 'single';
    case SelectedPool = 'selected_pool';
    case AutomaticPool = 'automatic_pool';
}
