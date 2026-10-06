<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\ContactSegment;
use Illuminate\View\View;

final class PlatformContactController extends Controller
{
    public function contacts(): View
    {
        return view('platform.contacts.index', ['title' => 'Contacts', 'rows' => Contact::with('tenant:id,name')->latest()->paginate(25), 'kind' => 'contacts']);
    }

    public function imports(): View
    {
        return view('platform.contacts.index', ['title' => 'Contact imports', 'rows' => ContactImport::latest()->paginate(25), 'kind' => 'imports']);
    }

    public function segments(): View
    {
        return view('platform.contacts.index', ['title' => 'Contact segments', 'rows' => ContactSegment::latest()->paginate(25), 'kind' => 'segments']);
    }
}
