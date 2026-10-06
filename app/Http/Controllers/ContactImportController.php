<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Jobs\ProcessContactImport;
use App\Models\Contact;
use App\Models\ContactImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class ContactImportController extends Controller
{
    public function index(TenantContext $c): View
    {
        return view('tenant.contacts.imports', ['imports' => ContactImport::where('tenant_id', $c->id())->latest()->paginate(20)]);
    }

    public function store(Request $r, TenantContext $c): RedirectResponse
    {
        $d = $r->validate(['file' => ['required', 'file', 'max:'.config('contacts.import_max_mb') * 1024, 'mimes:csv,xlsx'], 'duplicate_policy' => ['required', 'in:skip_existing,update_existing,create_only'], 'phone_column' => ['required', 'integer', 'min:0']]);
        $file = $r->file('file');
        $key = 'contact-imports/'.$c->id().'/'.Str::uuid().'.'.$file->extension();
        Storage::disk(config('contacts.import_disk'))->putFileAs(dirname($key), $file, basename($key));
        $batch = ContactImport::create(['tenant_id' => $c->id(), 'created_by' => $r->user()->id, 'status' => 'queued', 'disk' => config('contacts.import_disk'), 'storage_key' => $key, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'checksum_sha256' => hash_file('sha256', $file->getRealPath()), 'duplicate_policy' => $d['duplicate_policy'], 'mapping' => ['phone' => $d['phone_column']]]);
        ProcessContactImport::dispatch($batch->id);

        return back()->with('status', 'Private import queued.');
    }

    public function rollback(Request $r, string $importUuid, TenantContext $c): RedirectResponse
    {
        $batch = ContactImport::where('tenant_id', $c->id())->where('uuid', $importUuid)->firstOrFail();
        abort_if($batch->rolled_back_at, 422);
        Contact::where('tenant_id', $c->id())->where('source_reference', $batch->uuid)->whereNull('opted_out_at')->whereNull('suppressed_at')->where('updated_at', '<=', $batch->completed_at)->delete();
        $batch->update(['rolled_back_at' => now(), 'status' => 'rolled_back']);

        return back()->with('status', 'Eligible imported contacts rolled back; later safety changes were preserved.');
    }
}
