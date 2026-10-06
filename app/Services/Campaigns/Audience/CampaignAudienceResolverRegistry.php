<?php

namespace App\Services\Campaigns\Audience;

use App\Contracts\CampaignAudienceResolver;
use App\Enums\WhatsAppCampaignAudienceType;
use Illuminate\Validation\ValidationException;

final class CampaignAudienceResolverRegistry
{
    public function __construct(
        private AllEligibleContactsAudienceResolver $all,
        private SegmentAudienceResolver $segments,
        private GroupAudienceResolver $groups,
        private LabelAudienceResolver $labels,
        private ManualContactsAudienceResolver $manual,
    ) {}

    public function for(WhatsAppCampaignAudienceType $type): CampaignAudienceResolver
    {
        foreach ([$this->all, $this->segments, $this->groups, $this->labels, $this->manual] as $resolver) {
            if ($resolver->supports($type)) {
                return $resolver;
            }
        }
        throw ValidationException::withMessages(['audience_type' => 'Unsupported campaign audience type.']);
    }
}
