<?php

namespace App\Services\Campaigns;

use App\Data\Campaigns\CampaignPayload;
use App\Models\ContactSegment;
use App\Models\WhatsAppCampaign;

final class CampaignAudienceDefinitionHasher
{
    public function hash(WhatsAppCampaign $campaign): string
    {
        $refs = $campaign->audienceReferences()->orderBy('reference_uuid')->pluck('reference_uuid')->all();
        $marker = null;
        if ($campaign->audience_type->value === 'segment' && isset($refs[0])) {
            $marker = ContactSegment::where('tenant_id', $campaign->tenant_id)->where('uuid', $refs[0])->value('updated_at')?->format('c');
        }

        return hash('sha256', json_encode((new CampaignPayload(['type' => $campaign->audience_type->value, 'references' => $refs, 'configuration' => $campaign->audience_config ?? [], 'segment_marker' => $marker, 'eligibility_policy' => 'granted_only:v1']))->canonical(), JSON_THROW_ON_ERROR));
    }
}
