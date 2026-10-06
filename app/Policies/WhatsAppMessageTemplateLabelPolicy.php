<?php

namespace App\Policies;

use App\Contracts\TenantContext;
use App\Models\User;
use App\Models\WhatsAppMessageTemplateLabel;

final class WhatsAppMessageTemplateLabelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasTenantPermission(app(TenantContext::class)->get(), 'whatsapp_templates.view');
    }

    public function create(User $user): bool
    {
        return $user->hasTenantPermission(app(TenantContext::class)->get(), 'whatsapp_templates.manage_labels');
    }

    public function update(User $user, WhatsAppMessageTemplateLabel $label): bool
    {
        return $label->tenant_id === app(TenantContext::class)->id() && $this->create($user);
    }
}
