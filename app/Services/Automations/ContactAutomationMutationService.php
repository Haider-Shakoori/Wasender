<?php

namespace App\Services\Automations;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContactLabel;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Str;

final class ContactAutomationMutationService
{
    public function __construct(private AuditService $audit) {}

    public function label(Contact $contact, ContactLabel $label, bool $add, User $actor): bool
    {
        $exists = $label->contacts()->whereKey($contact->id)->exists();
        if ($add && ! $exists) {
            $label->contacts()->syncWithoutDetaching([$contact->id => ['uuid' => (string) Str::uuid(), 'tenant_id' => $contact->tenant_id, 'assigned_by' => $actor->id]]);
        }if (! $add && $exists) {
            $label->contacts()->detach($contact->id);
        }$changed = $add ? ! $exists : $exists;
        if ($changed) {
            $this->audit->recordDomain($add ? 'automation_workflow.contact_label_added' : 'automation_workflow.contact_label_removed', $actor, $contact->tenant, $contact, ['contact_uuid' => $contact->uuid, 'resource_uuid' => $label->uuid]);
        }

        return $changed;
    }

    public function group(Contact $contact, ContactGroup $group, bool $add, User $actor): bool
    {
        $exists = $group->contacts()->whereKey($contact->id)->exists();
        if ($add && ! $exists) {
            $group->contacts()->syncWithoutDetaching([$contact->id => ['uuid' => (string) Str::uuid(), 'tenant_id' => $contact->tenant_id, 'added_by' => $actor->id]]);
        }if (! $add && $exists) {
            $group->contacts()->detach($contact->id);
        }$changed = $add ? ! $exists : $exists;
        if ($changed) {
            $this->audit->recordDomain($add ? 'automation_workflow.contact_group_added' : 'automation_workflow.contact_group_removed', $actor, $contact->tenant, $contact, ['contact_uuid' => $contact->uuid, 'resource_uuid' => $group->uuid]);
        }

        return $changed;
    }

    public function update(Contact $contact, array $values, User $actor): array
    {
        $changed = [];
        foreach ($values as $field => $value) {
            if ($contact->{$field} !== $value) {
                $contact->{$field} = $value;
                $changed[] = $field;
            }
        }if ($changed) {
            $contact->save();
            $this->audit->recordDomain('automation_workflow.contact_updated', $actor, $contact->tenant, $contact, ['contact_uuid' => $contact->uuid, 'changed_fields' => $changed]);
        }

        return $changed;
    }
}
