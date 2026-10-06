<?php

namespace App\Policies;

use App\Contracts\TenantContext;
use App\Models\User;
use App\Models\WhatsAppConversationNote;

final class WhatsAppConversationNotePolicy
{
    private function allowed(User $u, WhatsAppConversationNote $n, string $p): bool
    {
        try {
            $t = app(TenantContext::class)->get();

            return $n->tenant_id === $t->id && $n->conversation->tenant_id === $t->id && $n->author_id === $u->id && $u->hasTenantPermission($t, $p);
        } catch (\Throwable) {
            return false;
        }
    }

    public function update(User $u, WhatsAppConversationNote $n): bool
    {
        return $this->allowed($u, $n, 'inbox.edit_own_notes');
    }

    public function delete(User $u, WhatsAppConversationNote $n): bool
    {
        return $this->allowed($u, $n, 'inbox.delete_own_notes');
    }
}
