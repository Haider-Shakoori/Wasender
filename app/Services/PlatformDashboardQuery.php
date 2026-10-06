<?php

namespace App\Services;

use App\Models\PlatformAuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PlatformDashboardQuery
{
    public function get(): array
    {
        return [
            'metrics' => [
                'tenants' => Tenant::query()->count(),
                'activeTenants' => Tenant::query()->active()->count(),
                'suspendedTenants' => Tenant::query()->suspended()->count(),
                'users' => User::query()->count(),
                'queued' => DB::table('jobs')->count(),
                'failed' => DB::table('failed_jobs')->count(),
            ],
            'recentEvents' => PlatformAuditLog::query()->with('actor:id,name,email')->latest('created_at')->limit(8)->get(),
        ];
    }
}
