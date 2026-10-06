<?php

namespace App\Policies;

use App\Contracts\TenantContext;
use App\Models\AutomationWorkflowExecution;
use App\Models\User;

final class AutomationWorkflowExecutionPolicy
{
    private function allowed(User $u, string $permission): bool
    {
        try {
            return $u->is_active && $u->hasTenantPermission(app(TenantContext::class)->get(), $permission);
        } catch (\Throwable) {
            return false;
        }
    }

    private function same(AutomationWorkflowExecution $e): bool
    {
        try {
            return $e->tenant_id === app(TenantContext::class)->id();
        } catch (\Throwable) {
            return false;
        }
    }

    public function viewAny(User $u): bool
    {
        return $this->allowed($u, 'automations.view_executions');
    }

    public function view(User $u, AutomationWorkflowExecution $e): bool
    {
        return $this->same($e) && $this->allowed($u, 'automations.view_executions');
    }

    public function cancel(User $u, AutomationWorkflowExecution $e): bool
    {
        return $this->same($e) && $this->allowed($u, 'automations.cancel_execution');
    }
}
