<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\TenantAuditLogQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TenantAuditLogController extends Controller
{
    public function index(Request $request, TenantAuditLogQuery $query): View
    {
        $this->authorize('viewAny', AuditLog::class);

        return view('tenant.audit.index', [
            'logs' => $query->paginate($request->string('search')->toString(), $request->string('action')->toString()),
            'actions' => $query->actions(),
        ]);
    }

    public function show(string $auditUuid, TenantAuditLogQuery $query): View
    {
        $log = $query->findByUuid($auditUuid);
        $this->authorize('view', $log);

        return view('tenant.audit.show', compact('log'));
    }
}
