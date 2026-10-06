<?php

namespace App\Http\Controllers;

use App\Models\PlatformAuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformAuditController extends Controller
{
    public function index(Request $request): View
    {
        $action = trim((string) $request->query('action'));
        $events = PlatformAuditLog::with('actor:id,name,email')->when($action, fn ($q) => $q->where('action', 'like', "%{$action}%"))->latest('created_at')->paginate(30)->withQueryString();

        return view('platform.audit.index', compact('events', 'action'));
    }
}
