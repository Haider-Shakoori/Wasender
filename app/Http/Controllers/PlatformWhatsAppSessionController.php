<?php

namespace App\Http\Controllers;

use App\Enums\WhatsAppSessionStatus;
use App\Jobs\ManageWhatsAppSession;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppSessionLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformWhatsAppSessionController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $sessions = WhatsAppSession::query()->with('tenant:id,uuid,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('uuid', $search)
                ->orWhereHas('tenant', fn ($tenant) => $tenant->where('name', 'like', "%{$search}%"))))
            ->latest('updated_at')->paginate(25)->withQueryString();

        return view('platform.whatsapp.index', compact('sessions', 'search'));
    }

    public function reconnect(WhatsAppSession $session, WhatsAppSessionLifecycleService $lifecycle): RedirectResponse
    {
        $target = $session->status === WhatsAppSessionStatus::Failed ? WhatsAppSessionStatus::Initializing : WhatsAppSessionStatus::Reconnecting;
        $lifecycle->transition($session, $target, 'platform');
        $session->increment('reconnect_attempts');
        $session->update(['last_reconnect_attempt_at' => now()]);
        ManageWhatsAppSession::dispatch($session->id, 'reconnect');

        return back()->with('status', 'Reconnect queued for the selected session.');
    }
}
