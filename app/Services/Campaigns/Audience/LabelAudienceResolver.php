<?php

namespace App\Services\Campaigns\Audience;

use App\Enums\WhatsAppCampaignAudienceType;
use App\Models\Contact;
use App\Models\ContactLabel;
use App\Models\Tenant;
use App\Models\WhatsAppCampaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class LabelAudienceResolver extends AbstractAudienceResolver
{
    public function supports(WhatsAppCampaignAudienceType $type): bool
    {
        return $type === WhatsAppCampaignAudienceType::Labels;
    }

    protected function query(Tenant $tenant, WhatsAppCampaign $campaign): Builder
    {
        $uuids = $campaign->audienceReferences()->pluck('reference_uuid');
        $ids = ContactLabel::where('tenant_id', $tenant->id)->whereIn('uuid', $uuids)->pluck('id');
        if ($ids->count() !== $uuids->unique()->count()) {
            throw ValidationException::withMessages(['audience' => 'A selected label is no longer available.']);
        }

        return Contact::withTrashed()->where('contacts.tenant_id', $tenant->id)->whereExists(fn ($q) => $q->selectRaw('1')->from('contact_label_assignments')->whereColumn('contact_label_assignments.contact_id', 'contacts.id')->where('contact_label_assignments.tenant_id', $tenant->id)->whereIn('contact_label_assignments.contact_label_id', $ids));
    }
}
