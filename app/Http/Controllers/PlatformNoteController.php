<?php

namespace App\Http\Controllers;

use App\Models\PlatformNote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlatformAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PlatformNoteController extends Controller
{
    public function store(Request $request, string $type, string $uuid, PlatformAuditService $audit): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']]);
        $subject = match ($type) {
            'tenant' => Tenant::where('uuid', $uuid)->firstOrFail(),'user' => User::where('uuid', $uuid)->firstOrFail(),default => abort(404)
        };
        PlatformNote::create(['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->id, 'author_id' => $request->user()->id, 'body' => $data['body']]);
        $audit->record('platform.note.created', $request->user(), $subject);

        return back()->with('status', 'Internal note added.');
    }
}
