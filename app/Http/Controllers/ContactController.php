<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Enums\ContactConsentStatus;
use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Services\ContactConsentService;
use App\Services\ContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ContactController extends Controller
{
    public function index(Request $r, TenantContext $c): View
    {
        $contacts = Contact::where('tenant_id', $c->id())->with(['groups:id,name', 'labels:id,name'])->when($r->search, fn ($q, $s) => $q->where(fn ($x) => $x->where('display_name', 'like', "%{$s}%")->orWhere('phone_normalized', 'like', '%'.preg_replace('/\D/', '', $s).'%')))->when($r->consent, fn ($q, $s) => $q->where('consent_status', $s))->latest()->paginate(20)->withQueryString();

        return view('tenant.contacts.index', compact('contacts'));
    }

    public function create(): View
    {
        return view('tenant.contacts.form', ['contact' => null]);
    }

    public function store(Request $r, TenantContext $c, ContactService $s): RedirectResponse
    {
        $contact = $s->save($c->get(), $r->user(), $this->data($r));

        return redirect()->route('tenant.contacts.show', $contact);
    }

    public function show(string $contactUuid, TenantContext $c): View
    {
        $contact = Contact::where('tenant_id', $c->id())->with(['events' => fn ($q) => $q->latest('occurred_at'), 'groups', 'labels'])->where('uuid', $contactUuid)->firstOrFail();

        return view('tenant.contacts.show', compact('contact'));
    }

    public function edit(string $contactUuid, TenantContext $c): View
    {
        return view('tenant.contacts.form', ['contact' => Contact::where('tenant_id', $c->id())->where('uuid', $contactUuid)->firstOrFail()]);
    }

    public function update(Request $r, string $contactUuid, TenantContext $c, ContactService $s): RedirectResponse
    {
        $contact = Contact::where('tenant_id', $c->id())->where('uuid', $contactUuid)->firstOrFail();
        $s->save($c->get(), $r->user(), $this->data($r), $contact);

        return redirect()->route('tenant.contacts.show', $contact);
    }

    public function consent(Request $r, string $contactUuid, TenantContext $c, ContactConsentService $s): RedirectResponse
    {
        $d = $r->validate(['status' => ['required', Rule::enum(ContactConsentStatus::class)], 'source' => ['required', 'in:manual,form,contract,existing_customer,other'], 'reason' => ['nullable', 'string', 'max:500']]);
        $contact = Contact::where('tenant_id', $c->id())->where('uuid', $contactUuid)->firstOrFail();
        $s->consent($contact, ContactConsentStatus::from($d['status']), $d['source'], $r->user(), $d['reason'] ?? null);

        return back()->with('status', 'Consent state recorded.');
    }

    public function exclusion(Request $r, string $contactUuid, string $action, TenantContext $c, ContactConsentService $s): RedirectResponse
    {
        abort_unless(in_array($action, ['opted_out', 'opted_in', 'suppressed', 'unsuppressed', 'blocked', 'unblocked']), 404);
        $contact = Contact::where('tenant_id', $c->id())->where('uuid', $contactUuid)->firstOrFail();
        $s->exclusion($contact, $action, $r->user(), $r->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null);

        return back()->with('status', 'Communication safety state updated.');
    }

    public function archive(string $contactUuid, TenantContext $c): RedirectResponse
    {
        $contact = Contact::where('tenant_id', $c->id())->where('uuid', $contactUuid)->firstOrFail();
        $contact->update(['status' => ContactStatus::Archived]);
        $contact->delete();

        return redirect()->route('tenant.contacts.index')->with('status', 'Contact archived.');
    }

    public function restore(string $contactUuid, TenantContext $c): RedirectResponse
    {
        $contact = Contact::withTrashed()->where('tenant_id', $c->id())->where('uuid', $contactUuid)->firstOrFail();
        abort_if(Contact::where('tenant_id', $c->id())->where('phone_normalized', $contact->phone_normalized)->exists(), 422);
        $contact->restore();
        $contact->update(['status' => ContactStatus::Active]);

        return back()->with('status', 'Contact restored.');
    }

    private function data(Request $r): array
    {
        return $r->validate(['first_name' => ['nullable', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'], 'display_name' => ['nullable', 'string', 'max:180'], 'company' => ['nullable', 'string', 'max:180'], 'email' => ['nullable', 'email', 'max:254'], 'phone' => ['required', 'string', 'max:40'], 'notes' => ['nullable', 'string', 'max:5000']]);
    }
}
