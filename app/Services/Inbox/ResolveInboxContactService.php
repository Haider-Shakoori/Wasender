<?php

namespace App\Services\Inbox;

use App\Enums\ContactConsentStatus;
use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\Tenant;
use App\Services\PhoneNumberNormalizer;

final class ResolveInboxContactService
{
    public function __construct(private PhoneNumberNormalizer $phones) {}

    public function resolve(Tenant $tenant, string $phone): Contact
    {
        $normalized = $this->phones->normalize($phone);
        $existing = Contact::query()->where('tenant_id', $tenant->id)->where('phone_normalized', $normalized->e164)->first();
        if ($existing) {
            return $existing;
        }

        return Contact::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone_normalized' => $normalized->e164],
            ['phone_input' => $normalized->input, 'whatsapp_address' => $normalized->whatsappAddress, 'status' => ContactStatus::Active, 'consent_status' => ContactConsentStatus::Unknown, 'source' => ContactSource::System, 'created_by' => $tenant->owner_id],
        );
    }
}
