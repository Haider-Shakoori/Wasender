<?php

namespace App\Policies;

use App\Contracts\TenantContext;
use App\Models\User;
use App\Models\WhatsAppCampaign;

final class WhatsAppCampaignPolicy
{
    private function permission(User $u, string $p): bool
    {
        try {
            $t = app(TenantContext::class)->get();

            return $u->hasTenantPermission($t, $p);
        } catch (\Throwable) {
            return false;
        }
    }

    private function same(WhatsAppCampaign $c): bool
    {
        try {
            return $c->tenant_id === app(TenantContext::class)->id();
        } catch (\Throwable) {
            return false;
        }
    }

    public function viewAny(User $u): bool
    {
        return $this->permission($u, 'campaigns.view');
    }

    public function view(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && $this->permission($u, 'campaigns.view');
    }

    public function create(User $u): bool
    {
        return $this->permission($u, 'campaigns.create');
    }

    public function update(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && $c->status->editable() && $this->permission($u, 'campaigns.update');
    }

    public function duplicate(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && $this->permission($u, 'campaigns.duplicate');
    }

    public function schedule(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && $c->status->value === 'ready' && $this->permission($u, 'campaigns.schedule');
    }

    public function unschedule(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && $c->status->value === 'scheduled' && $this->permission($u, 'campaigns.schedule');
    }

    public function archive(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && in_array($c->status->value, ['draft', 'needs_attention', 'ready', 'failed', 'cancelled', 'completed', 'completed_with_errors'], true) && $this->permission($u, 'campaigns.archive');
    }

    public function viewEvents(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && $this->permission($u, 'campaigns.view_events');
    }

    public function viewContent(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && $this->permission($u, 'campaigns.view_content');
    }

    public function prepare(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && in_array($c->status->value, ['ready', 'scheduled'], true) && $this->permission($u, 'campaigns.prepare');
    }

    public function launch(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && $c->status->value === 'prepared' && $this->permission($u, 'campaigns.launch');
    }

    public function pause(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && in_array($c->status->value, ['queued', 'running', 'pausing'], true) && $this->permission($u, 'campaigns.pause');
    }

    public function resume(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && $c->status->value === 'paused' && $this->permission($u, 'campaigns.resume');
    }

    public function cancel(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && in_array($c->status->value, ['queued', 'running', 'pausing', 'paused', 'resuming', 'cancelling'], true) && $this->permission($u, 'campaigns.cancel');
    }

    public function viewRecipients(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && $this->permission($u, 'campaigns.view_recipients');
    }

    public function retryPreparation(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && in_array($c->status->value, ['needs_attention', 'failed', 'ready'], true) && $this->permission($u, 'campaigns.prepare');
    }

    public function cancelPreparation(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && $c->status->value === 'preparing' && $this->permission($u, 'campaigns.prepare');
    }

    public function invalidatePreparation(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && $c->status->value === 'prepared' && $this->permission($u, 'campaigns.prepare');
    }

    public function viewExclusions(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && $this->permission($u, 'campaigns.view_recipients');
    }

    public function viewExecution(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && $this->permission($u, 'campaigns.view_execution');
    }

    public function viewRecipientExecution(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && $this->permission($u, 'campaigns.view_execution') && $this->permission($u, 'campaigns.view_recipients');
    }

    public function retryRecipient(User $u, WhatsAppCampaign $c): bool
    {
        return $this->same($c) && $c->status->value === 'running' && $this->permission($u, 'campaigns.retry_failed');
    }
}
