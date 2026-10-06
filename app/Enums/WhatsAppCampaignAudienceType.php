<?php

namespace App\Enums;

enum WhatsAppCampaignAudienceType: string
{
    case AllEligibleContacts = 'all_eligible_contacts';
    case Segment = 'segment';
    case Groups = 'groups';
    case Labels = 'labels';
    case ManualContacts = 'manual_contacts';
}
