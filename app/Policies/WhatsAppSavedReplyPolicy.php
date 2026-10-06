<?php

namespace App\Policies;

use App\Contracts\TenantContext;
use App\Models\User;
use App\Models\WhatsAppSavedReply;

final class WhatsAppSavedReplyPolicy
{
    private function permission(User $u): bool
    {
        try {
            $t = app(TenantContext::class)->get();

            return $u->hasTenantPermission($t, 'inbox.manage_saved_replies');
        } catch (\Throwable) {
            return false;
        }
    }

    public function viewAny(User $u): bool
    {
        return $this->permission($u);
    }

    public function create(User $u): bool
    {
        return $this->permission($u);
    }

    public function update(User $u, WhatsAppSavedReply $r): bool
    {
        try {
            return $r->tenant_id === app(TenantContext::class)->id() && $this->permission($u);
        } catch (\Throwable) {
            return false;
        }
    }
}
