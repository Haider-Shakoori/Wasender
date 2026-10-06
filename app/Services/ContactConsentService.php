<?php

namespace App\Services;

use App\Enums\ContactConsentStatus;
use App\Models\Contact;
use App\Models\ContactConsentEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ContactConsentService
{
    public function consent(Contact $contact, ContactConsentStatus $to, string $source, User $actor, ?string $reason = null): Contact
    {
        return $this->mutate($contact, 'consent.'.$to->value, $actor, function (Contact $c) use ($to, $source) {
            $from = $c->consent_status;
            $c->update(['consent_status' => $to, 'consent_source' => $source, 'consent_recorded_at' => now()]);

            return [$from->value, $to->value];
        }, $reason);
    }

    public function exclusion(Contact $contact, string $action, User $actor, ?string $reason = null): Contact
    {
        return $this->mutate($contact, 'contact.'.$action, $actor, function (Contact $c) use ($action, $reason) {
            $changes = match ($action) {
                'opted_out' => ['opted_out_at' => now(), 'opt_out_source' => $reason ?: 'manual', 'consent_status' => ContactConsentStatus::Withdrawn],'opted_in' => ['opted_out_at' => null, 'opt_out_source' => null],'suppressed' => ['suppressed_at' => now(), 'suppression_reason' => $reason],'unsuppressed' => ['suppressed_at' => null, 'suppression_reason' => null],'blocked' => ['blocked_at' => now()],'unblocked' => ['blocked_at' => null]
            };
            $c->update($changes);

            return [null, $c->consent_status->value];
        }, $reason);
    }

    private function mutate(Contact $contact, string $event, User $actor, callable $change, ?string $reason): Contact
    {
        return DB::transaction(function () use ($contact, $event, $actor, $change, $reason) {
            $locked = Contact::lockForUpdate()->findOrFail($contact->id);
            [$from,$to] = $change($locked);
            ContactConsentEvent::create(['tenant_id' => $locked->tenant_id, 'contact_id' => $locked->id, 'event' => $event, 'from_status' => $from, 'to_status' => $to, 'source' => 'manual', 'actor_id' => $actor->id, 'reason' => str($reason)->limit(500), 'occurred_at' => now(), 'created_at' => now()]);

            return $locked->refresh();
        }, 3);
    }
}
