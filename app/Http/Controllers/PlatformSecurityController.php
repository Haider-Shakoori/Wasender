<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class PlatformSecurityController extends Controller
{
    public function __invoke(): View
    {
        return view('platform.security', ['metrics' => ['active_users' => User::where('status', 'active')->count(), 'active_sessions' => config('session.driver') === 'database' ? DB::table(config('session.table'))->where('last_activity', '>=', now()->subMinutes(config('session.lifetime'))->timestamp)->count() : 0, 'integration_tokens' => Integration::whereNotNull('credentials_encrypted')->count(), 'disabled_integrations' => Integration::where('is_enabled', false)->count(), 'recent_rotations' => AuditLog::where('action', 'integration.credentials_rotated')->where('created_at', '>=', now()->subDays(30))->count(), 'recent_login_failures' => AuditLog::where('action', 'user.login_failed')->where('created_at', '>=', now()->subDay())->count()], 'actions' => AuditLog::whereIn('action', ['user.login_failed', 'user.password_changed', 'user.password_reset', 'user.sessions_revoked', 'user.session_revoked', 'integration.credentials_created', 'integration.credentials_rotated', 'integration.credentials_revoked'])->latest()->limit(25)->get()]);
    }
}
