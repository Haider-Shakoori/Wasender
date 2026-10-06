<?php

namespace App\Services\Campaigns\Audience;

use App\Enums\WhatsAppCampaignAudienceType;
use App\Models\Contact;
use App\Models\ContactSegment;
use App\Models\Tenant;
use App\Models\WhatsAppCampaign;
use App\Services\ContactSegmentCompiler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class SegmentAudienceResolver extends AbstractAudienceResolver
{
    public function __construct(private ContactSegmentCompiler $compiler) {}

    public function supports(WhatsAppCampaignAudienceType $type): bool
    {
        return $type === WhatsAppCampaignAudienceType::Segment;
    }

    protected function query(Tenant $tenant, WhatsAppCampaign $campaign): Builder
    {
        $uuid = $campaign->audienceReferences()->value('reference_uuid');
        $segment = ContactSegment::where('tenant_id', $tenant->id)->where('uuid', $uuid)->first();
        if (! $segment) {
            throw ValidationException::withMessages(['audience' => 'The saved segment is no longer available.']);
        }

        return $this->compiler->apply(Contact::withTrashed()->where('tenant_id', $tenant->id), $segment->definition);
    }
}
