<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContactLabel;
use App\Models\ContactSegment;
use App\Services\ContactSegmentCompiler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class ContactDirectoryController extends Controller
{
    public function groups(TenantContext $c): View
    {
        return view('tenant.contacts.taxonomy', ['title' => 'Groups', 'items' => ContactGroup::where('tenant_id', $c->id())->withCount('contacts')->paginate(20), 'kind' => 'groups']);
    }

    public function labels(TenantContext $c): View
    {
        return view('tenant.contacts.taxonomy', ['title' => 'Labels', 'items' => ContactLabel::where('tenant_id', $c->id())->withCount('contacts')->paginate(20), 'kind' => 'labels']);
    }

    public function store(Request $r, string $kind, TenantContext $c, TenantEntitlements $entitlements): RedirectResponse
    {
        $d = $r->validate(['name' => ['required', 'string', 'max:120'], 'color' => ['nullable', 'in:slate,blue,green,amber,red,violet']]);
        $class = $kind === 'groups' ? ContactGroup::class : ContactLabel::class;
        abort_unless(in_array($kind, ['groups', 'labels']), 404);
        $entitlements->requireFeature('contacts.manage');
        $entitlements->requireCapacity($kind === 'groups' ? 'contact_groups.max' : 'contact_labels.max');
        $values = ['tenant_id' => $c->id(), 'name' => $d['name'], 'color' => $d['color'] ?? 'slate', 'created_by' => $r->user()->id];
        if ($kind === 'labels') {
            $values['slug'] = Str::slug($d['name']);
        }
        $class::create($values);

        return back()->with('status', str($kind)->singular()->headline().' created.');
    }

    public function assign(Request $r, string $kind, string $uuid, TenantContext $c): RedirectResponse
    {
        $data = $r->validate(['contact_uuids' => ['required', 'array', 'max:500'], 'contact_uuids.*' => ['uuid'], 'mode' => ['required', 'in:add,remove']]);
        $class = $kind === 'groups' ? ContactGroup::class : ContactLabel::class;
        abort_unless(in_array($kind, ['groups', 'labels']), 404);
        $item = $class::where('tenant_id', $c->id())->where('uuid', $uuid)->firstOrFail();
        $ids = Contact::where('tenant_id', $c->id())->whereIn('uuid', $data['contact_uuids'])->pluck('id');
        if ($data['mode'] === 'remove') {
            $item->contacts()->detach($ids);
        } else {
            foreach ($ids as $id) {
                $item->contacts()->syncWithoutDetaching([$id => ['uuid' => (string) Str::uuid(), 'tenant_id' => $c->id(), $kind === 'groups' ? 'added_by' : 'assigned_by' => $r->user()->id]]);
            }
        }

        return back()->with('status', 'Contact assignments updated.');
    }

    public function segments(TenantContext $c): View
    {
        return view('tenant.contacts.segments', ['segments' => ContactSegment::where('tenant_id', $c->id())->paginate(20)]);
    }

    public function segmentStore(Request $r, TenantContext $c, ContactSegmentCompiler $compiler, TenantEntitlements $entitlements): RedirectResponse
    {
        $d = $r->validate(['name' => ['required', 'string', 'max:120'], 'field' => ['required', 'string'], 'operator' => ['required', 'string'], 'value' => ['nullable', 'string', 'max:180']]);
        $definition = ['logic' => 'and', 'rules' => [['field' => $d['field'], 'operator' => $d['operator'], 'value' => $d['value'] ?? null]]];
        $entitlements->requireFeature('contacts.manage');
        $entitlements->requireCapacity('contact_segments.max');
        $compiler->apply(Contact::query(), $definition);
        ContactSegment::create(['tenant_id' => $c->id(), 'name' => $d['name'], 'definition' => $definition, 'summary' => "{$d['field']} {$d['operator']}", 'created_by' => $r->user()->id]);

        return back()->with('status', 'Dynamic segment saved.');
    }
}
