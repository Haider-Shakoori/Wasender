<?php

namespace App\Services\Campaigns\Audience;

use App\Contracts\CampaignAudienceResolver;
use App\Data\Campaigns\AudienceCursor;
use App\Data\Campaigns\AudienceEstimate;
use App\Data\Campaigns\CampaignAudienceCandidate;
use App\Models\Tenant;
use App\Models\WhatsAppCampaign;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

abstract class AbstractAudienceResolver implements CampaignAudienceResolver
{
    abstract protected function query(Tenant $tenant, WhatsAppCampaign $campaign): Builder;

    public function estimate(Tenant $tenant, WhatsAppCampaign $campaign): AudienceEstimate
    {
        return new AudienceEstimate((clone $this->query($tenant, $campaign))->count(), null, false, ['This estimate may change before preparation.'], CarbonImmutable::now());
    }

    public function candidates(Tenant $tenant, WhatsAppCampaign $campaign, ?AudienceCursor $cursor = null): iterable
    {
        $query = $this->query($tenant, $campaign)->when($cursor?->afterContactId, fn (Builder $q, int $id) => $q->where('contacts.id', '>', $id))->orderBy('contacts.id');
        foreach ($query->select(['contacts.id', 'contacts.uuid'])->cursor() as $contact) {
            yield new CampaignAudienceCandidate($contact->uuid, $contact->id, $campaign->audience_type->value, $this->sourceReference($campaign));
        }
    }

    protected function sourceReference(WhatsAppCampaign $campaign): ?string
    {
        return $campaign->audienceReferences()->orderBy('reference_uuid')->value('reference_uuid');
    }
}
