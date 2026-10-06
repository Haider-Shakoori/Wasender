<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\TenantAuthorization;
use App\Contracts\TenantContext;
use App\Models\AuditLog;
use App\Models\User;

final class AuditLogPolicy
{
    public function __construct(private TenantAuthorization $auth, private TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->auth->allows('audit_logs.view');
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $log->tenant_id === $this->context->id() && $this->auth->allows('audit_logs.view');
    }
}
