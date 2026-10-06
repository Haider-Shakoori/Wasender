<?php

namespace App\Services\Campaigns\Audience;

use App\Enums\WhatsAppCampaignAudienceType;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\WhatsAppCampaign;
use Illuminate\Database\Eloquent\Builder;

final class AllEligibleContactsAudienceResolver extends AbstractAudienceResolver
{
    public function supports(WhatsAppCampaignAudienceType $type): bool
    {
        return $type === WhatsAppCampaignAudienceType::AllEligibleContacts;
    }

    protected function query(Tenant $tenant, WhatsAppCampaign $campaign): Builder
    {
        return Contact::withTrashed()->where('tenant_id', $tenant->id);
    }
}
