<?php

namespace App\Policies;

use App\Contracts\TenantContext;
use App\Models\User;
use App\Models\WhatsAppInboxMessage;

final class WhatsAppInboxMessagePolicy
{
    public function view(User $user, WhatsAppInboxMessage $message): bool
    {
        try {
            $tenant = app(TenantContext::class)->get();

            return $message->tenant_id === $tenant->id && $message->session->tenant_id === $tenant->id && $user->hasTenantPermission($tenant, 'inbox.view_messages');
        } catch (\Throwable) {
            return false;
        }
    }
}
