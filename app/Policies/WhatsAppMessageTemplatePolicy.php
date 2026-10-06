<?php

namespace App\Policies;

use App\Contracts\TenantContext;
use App\Models\User;
use App\Models\WhatsAppMessageTemplate;

final class WhatsAppMessageTemplatePolicy
{
    private function permission(User $user, string $permission): bool
    {
        try {
            return $user->hasTenantPermission(app(TenantContext::class)->get(), $permission);
        } catch (\Throwable) {
            return false;
        }
    }

    private function same(WhatsAppMessageTemplate $template): bool
    {
        try {
            return $template->tenant_id === app(TenantContext::class)->id();
        } catch (\Throwable) {
            return false;
        }
    }

    public function viewAny(User $user): bool
    {
        return $this->permission($user, 'whatsapp_templates.view');
    }

    public function view(User $user, WhatsAppMessageTemplate $template): bool
    {
        return $this->same($template) && $this->permission($user, 'whatsapp_templates.view');
    }

    public function create(User $user): bool
    {
        return $this->permission($user, 'whatsapp_templates.create');
    }

    public function update(User $user, WhatsAppMessageTemplate $template): bool
    {
        return $this->same($template) && $this->permission($user, 'whatsapp_templates.update');
    }

    public function publish(User $user, WhatsAppMessageTemplate $template): bool
    {
        return $this->same($template) && $this->permission($user, 'whatsapp_templates.publish');
    }

    public function duplicate(User $user, WhatsAppMessageTemplate $template): bool
    {
        return $this->same($template) && $this->permission($user, 'whatsapp_templates.duplicate');
    }

    public function archive(User $user, WhatsAppMessageTemplate $template): bool
    {
        return $this->same($template) && $this->permission($user, 'whatsapp_templates.archive');
    }

    public function restore(User $user, WhatsAppMessageTemplate $template): bool
    {
        return $this->same($template) && $this->permission($user, 'whatsapp_templates.restore');
    }

    public function viewVersions(User $user, WhatsAppMessageTemplate $template): bool
    {
        return $this->same($template) && $this->permission($user, 'whatsapp_templates.view_versions');
    }

    public function manageMedia(User $user, WhatsAppMessageTemplate $template): bool
    {
        return $this->same($template) && $this->permission($user, 'whatsapp_templates.manage_media');
    }

    public function manageLabels(User $user, WhatsAppMessageTemplate $template): bool
    {
        return $this->same($template) && $this->permission($user, 'whatsapp_templates.manage_labels');
    }

    public function manageCategory(User $user, WhatsAppMessageTemplate $template): bool
    {
        return $this->same($template) && $this->permission($user, 'whatsapp_templates.manage_categories');
    }

    public function use(User $user, WhatsAppMessageTemplate $template): bool
    {
        return $this->same($template) && $this->permission($user, 'whatsapp_templates.use');
    }
}
