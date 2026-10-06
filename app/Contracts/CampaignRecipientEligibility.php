<?php

namespace App\Contracts;

use App\Data\Campaigns\EligibilityDecision;
use App\Models\Contact;
use App\Models\WhatsAppCampaign;

interface CampaignRecipientEligibility
{
    public function evaluate(Contact $contact, WhatsAppCampaign $campaign): EligibilityDecision;
}
