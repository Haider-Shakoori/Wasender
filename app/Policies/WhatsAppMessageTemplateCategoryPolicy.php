<?php

namespace App\Policies;

use App\Contracts\TenantContext;
use App\Models\User;
use App\Models\WhatsAppMessageTemplateCategory;

final class WhatsAppMessageTemplateCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasTenantPermission(app(TenantContext::class)->get(), 'whatsapp_templates.view');
    }

    public function create(User $user): bool
    {
        return $user->hasTenantPermission(app(TenantContext::class)->get(), 'whatsapp_templates.manage_categories');
    }

    public function update(User $user, WhatsAppMessageTemplateCategory $category): bool
    {
        return $category->tenant_id === app(TenantContext::class)->id() && $this->create($user);
    }
}
