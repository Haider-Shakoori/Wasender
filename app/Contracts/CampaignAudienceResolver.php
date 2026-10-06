<?php

namespace App\Contracts;

use App\Data\Campaigns\AudienceCursor;
use App\Data\Campaigns\AudienceEstimate;
use App\Enums\WhatsAppCampaignAudienceType;
use App\Models\Tenant;
use App\Models\WhatsAppCampaign;

interface CampaignAudienceResolver
{
    public function supports(WhatsAppCampaignAudienceType $type): bool;

    public function estimate(Tenant $tenant, WhatsAppCampaign $campaign): AudienceEstimate;

    public function candidates(Tenant $tenant, WhatsAppCampaign $campaign, ?AudienceCursor $cursor = null): iterable;
}
