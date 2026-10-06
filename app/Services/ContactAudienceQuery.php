<?php

namespace App\Services;

use App\Contracts\ContactAudienceResolver;
use App\Enums\ContactConsentStatus;
use App\Enums\ContactStatus;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;

final class ContactAudienceQuery implements ContactAudienceResolver
{
    public function __construct(private ContactSegmentCompiler $segments) {}

    public function query(int $tenantId, ?array $segment = null): Builder
    {
        $q = Contact::where('tenant_id', $tenantId)->where('status', ContactStatus::Active)->where('consent_status', ContactConsentStatus::Granted)->whereNull('opted_out_at')->whereNull('suppressed_at')->whereNull('blocked_at')->where(fn ($x) => $x->whereNull('consent_expires_at')->orWhere('consent_expires_at', '>', now()));

        return $segment ? $this->segments->apply($q, $segment) : $q;
    }
}
