<?php

namespace App\Services;

use App\Enums\ContactConsentStatus;
use App\Enums\ContactStatus;
use App\Models\Contact;

final class ContactEligibilityService
{
    public function eligible(Contact $contact): bool
    {
        return $contact->status === ContactStatus::Active && $contact->consent_status === ContactConsentStatus::Granted && ! $contact->opted_out_at && ! $contact->suppressed_at && ! $contact->blocked_at && (! $contact->consent_expires_at || $contact->consent_expires_at->isFuture());
    }
}
