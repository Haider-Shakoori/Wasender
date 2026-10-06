<?php

namespace App\Services\Campaigns\Audience;

use App\Enums\WhatsAppCampaignAudienceType;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Tenant;
use App\Models\WhatsAppCampaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class GroupAudienceResolver extends AbstractAudienceResolver
{
    public function supports(WhatsAppCampaignAudienceType $type): bool
    {
        return $type === WhatsAppCampaignAudienceType::Groups;
    }

    protected function query(Tenant $tenant, WhatsAppCampaign $campaign): Builder
    {
        $uuids = $campaign->audienceReferences()->pluck('reference_uuid');
        $ids = ContactGroup::where('tenant_id', $tenant->id)->whereIn('uuid', $uuids)->pluck('id');
        if ($ids->count() !== $uuids->unique()->count()) {
            throw ValidationException::withMessages(['audience' => 'A selected group is no longer available.']);
        }

        return Contact::withTrashed()->where('contacts.tenant_id', $tenant->id)->whereExists(fn ($q) => $q->selectRaw('1')->from('contact_group_members')->whereColumn('contact_group_members.contact_id', 'contacts.id')->where('contact_group_members.tenant_id', $tenant->id)->whereIn('contact_group_members.contact_group_id', $ids));
    }
}
