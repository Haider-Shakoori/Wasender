<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Support\SensitiveDataMasker;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class AccountSecurityController extends Controller
{
    public function show(Request $request): View
    {
        $sessions = collect();
        if (config('session.driver') === 'database') {
            $sessions = DB::table(config('session.table'))->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get()->map(fn ($session) => (object) ['key' => SensitiveDataMasker::session($session->id), 'agent' => str($session->user_agent ?: 'Unknown device')->limit(100)->toString(), 'ip' => SensitiveDataMasker::ip($session->ip_address), 'last_activity' => Carbon::createFromTimestamp($session->last_activity), 'current' => hash_equals($session->id, $request->session()->getId())]);
        }
        $actions = $request->user()->auditLogs()->whereIn('action', ['user.password_changed', 'user.password_reset', 'user.sessions_revoked', 'user.session_revoked', 'integration.credentials_created', 'integration.credentials_rotated', 'integration.credentials_revoked'])->latest()->limit(10)->get();

        return view('tenant.account.security', compact('sessions', 'actions'));
    }

    public function password(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', Password::min(8)]]);
        $request->user()->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
        $this->deleteOtherSessions($request);
        $request->session()->regenerate();
        $audit->recordDomain('user.password_changed', $request->user(), $request->user()->lastActiveTenant, $request->user());

        return back()->with('status', 'Password updated and other sessions revoked.');
    }

    public function revokeOthers(Request $request, AuditService $audit): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $count = $this->deleteOtherSessions($request);
        $audit->recordDomain('user.sessions_revoked', $request->user(), $request->user()->lastActiveTenant, $request->user(), ['revoked_count' => $count]);

        return back()->with('status', 'Other sessions logged out.');
    }

    public function revoke(Request $request, string $session, AuditService $audit): RedirectResponse
    {
        abort_unless(config('session.driver') === 'database', 404);
        $record = DB::table(config('session.table'))->where('user_id', $request->user()->id)->get()->first(fn ($row) => hash_equals(SensitiveDataMasker::session($row->id), $session));
        abort_unless($record && ! hash_equals($record->id, $request->session()->getId()), 404);
        DB::table(config('session.table'))->where('id', $record->id)->delete();
        $audit->recordDomain('user.session_revoked', $request->user(), $request->user()->lastActiveTenant, $request->user());

        return back()->with('status', 'Session logged out.');
    }

    private function deleteOtherSessions(Request $request): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::table(config('session.table'))->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
    }
}
