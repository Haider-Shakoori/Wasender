<?php

namespace App\Policies;

use App\Contracts\TenantContext;
use App\Models\User;
use App\Models\WhatsAppConversation;

final class WhatsAppConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'inbox.view_conversations');
    }

    public function view(User $user, WhatsAppConversation $conversation): bool
    {
        return $this->same($conversation) && $this->allowed($user, 'inbox.view_conversations');
    }

    public function assign(User $user, WhatsAppConversation $conversation): bool
    {
        return $this->same($conversation) && $this->allowed($user, 'inbox.assign');
    }

    public function changeStatus(User $user, WhatsAppConversation $conversation): bool
    {
        return $this->same($conversation) && $this->allowed($user, 'inbox.change_status');
    }

    public function changePriority(User $user, WhatsAppConversation $conversation): bool
    {
        return $this->same($conversation) && $this->allowed($user, 'inbox.change_priority');
    }

    public function markRead(User $user, WhatsAppConversation $conversation): bool
    {
        return $this->same($conversation) && $this->allowed($user, 'inbox.mark_read');
    }

    public function addNote(User $user, WhatsAppConversation $conversation): bool
    {
        return $this->same($conversation) && $this->allowed($user, 'inbox.add_notes');
    }

    public function manageLabels(User $user, WhatsAppConversation $conversation): bool
    {
        return $this->same($conversation) && $this->allowed($user, 'inbox.manage_labels');
    }

    public function viewActivity(User $user, WhatsAppConversation $conversation): bool
    {
        return $this->same($conversation) && $this->allowed($user, 'inbox.view_activity');
    }

    public function reply(User $user, WhatsAppConversation $conversation): bool
    {
        return $this->same($conversation) && $conversation->status->value !== 'archived' && $this->allowed($user, 'inbox.reply');
    }

    private function same(WhatsAppConversation $conversation): bool
    {
        try {
            return $conversation->tenant_id === app(TenantContext::class)->id() && $conversation->session->tenant_id === $conversation->tenant_id;
        } catch (\Throwable) {
            return false;
        }
    }

    private function allowed(User $user, string $permission): bool
    {
        try {
            return $user->hasTenantPermission(app(TenantContext::class)->get(), $permission);
        } catch (\Throwable) {
            return false;
        }
    }
}
