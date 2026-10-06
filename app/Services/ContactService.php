<?php

namespace App\Services;

use App\Contracts\TenantEntitlements;
use App\Enums\ContactConsentStatus;
use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ContactService
{
    public function __construct(private PhoneNumberNormalizer $phones, private TenantEntitlements $entitlements, private AuditService $audit) {}

    public function save(Tenant $tenant, User $actor, array $data, ?Contact $contact = null): Contact
    {
        $phone = $this->phones->normalize($data['phone']);
        if (! $contact) {
            $this->entitlements->requireFeature('contacts.manage');
            $this->entitlements->requireCapacity('contacts.max');
        }

        return DB::transaction(function () use ($tenant, $actor, $data, $contact, $phone) {
            $model = $contact ? Contact::where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($contact->id) : new Contact(['tenant_id' => $tenant->id, 'created_by' => $actor->id, 'source' => ContactSource::Manual]);
            $model->fill(collect($data)->only(['first_name', 'last_name', 'display_name', 'company', 'email', 'notes'])->all() + ['phone_input' => $phone->input, 'phone_normalized' => $phone->e164, 'whatsapp_address' => $phone->whatsappAddress, 'status' => $model->status ?: ContactStatus::Active, 'consent_status' => $model->consent_status ?: ContactConsentStatus::Unknown, 'updated_by' => $actor->id]);
            $model->save();
            $this->audit->recordDomain($contact ? 'contact.updated' : 'contact.created', $actor, $tenant, $model, ['contact_uuid' => $model->uuid]);

            return $model;
        }, 3);
    }
}
