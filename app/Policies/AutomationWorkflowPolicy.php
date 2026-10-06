<?php

namespace App\Policies;

use App\Contracts\TenantContext;
use App\Enums\AutomationWorkflowStatus;
use App\Models\AutomationWorkflow;
use App\Models\User;

final class AutomationWorkflowPolicy
{
    private function permission(User $u, string $p): bool
    {
        try {
            return $u->is_active && $u->hasTenantPermission(app(TenantContext::class)->get(), $p);
        } catch (\Throwable) {
            return false;
        }
    }

    private function same(AutomationWorkflow $w): bool
    {
        try {
            return $w->tenant_id === app(TenantContext::class)->id();
        } catch (\Throwable) {
            return false;
        }
    }

    public function viewAny(User $u): bool
    {
        return $this->permission($u, 'automations.view');
    }

    public function view(User $u, AutomationWorkflow $w): bool
    {
        return $this->same($w) && $this->permission($u, 'automations.view');
    }

    public function create(User $u): bool
    {
        return $this->permission($u, 'automations.create');
    }

    public function update(User $u, AutomationWorkflow $w): bool
    {
        return $this->same($w) && $w->status !== AutomationWorkflowStatus::Archived && $this->permission($u, 'automations.update');
    }

    public function publish(User $u, AutomationWorkflow $w): bool
    {
        return $this->same($w) && $w->status !== AutomationWorkflowStatus::Archived && $this->permission($u, 'automations.publish');
    }

    public function enable(User $u, AutomationWorkflow $w): bool
    {
        return $this->same($w) && $w->status !== AutomationWorkflowStatus::Archived && $this->permission($u, 'automations.enable');
    }

    public function disable(User $u, AutomationWorkflow $w): bool
    {
        return $this->same($w) && $this->permission($u, 'automations.disable');
    }

    public function duplicate(User $u, AutomationWorkflow $w): bool
    {
        return $this->same($w) && $this->permission($u, 'automations.duplicate');
    }

    public function archive(User $u, AutomationWorkflow $w): bool
    {
        return $this->same($w) && $this->permission($u, 'automations.archive');
    }

    public function restore(User $u, AutomationWorkflow $w): bool
    {
        return $this->same($w) && $this->permission($u, 'automations.restore');
    }

    public function viewVersions(User $u, AutomationWorkflow $w): bool
    {
        return $this->same($w) && $this->permission($u, 'automations.view_versions');
    }

    public function execute(User $u, AutomationWorkflow $w): bool
    {
        return $this->same($w) && $this->permission($u, 'automations.execute');
    }
}
