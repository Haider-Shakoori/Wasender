<?php

namespace App\Services\Inbox;

use App\Enums\MembershipStatus;
use App\Enums\WhatsAppConversationPriority;
use App\Enums\WhatsAppConversationStatus;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WhatsAppConversationOperations
{
    public function __construct(private ConversationActivityRecorder $activity, private AuditService $audit) {}

    public function assign(WhatsAppConversation $conversation, User $actor, ?string $userUuid): WhatsAppConversation
    {
        return DB::transaction(function () use ($conversation, $actor, $userUuid) {
            $c = $this->lock($conversation);
            $assignee = null;
            if ($userUuid) {
                $membership = TenantMembership::forTenant($c->tenant_id)->where('status', MembershipStatus::Active)->whereHas('user', fn ($q) => $q->where('uuid', $userUuid)->where('status', 'active'))->with('user')->first();
                if (! $membership) {
                    throw ValidationException::withMessages(['user_uuid' => 'Select an active workspace member.']);
                } $assignee = $membership->user;
            } if ($c->assigned_user_id === $assignee?->id) {
                return $c;
            } $previous = $c->assignedUser?->uuid;
            $c->update(['assigned_user_id' => $assignee?->id, 'last_agent_activity_at' => now()]);
            $type = $assignee ? 'assigned' : 'unassigned';
            $meta = ['previous_user_uuid' => $previous, 'new_user_uuid' => $assignee?->uuid];
            $this->activity->record($c, $actor, $type, $meta);
            $this->audit->recordDomain('whatsapp_inbox.conversation_'.$type, $actor, $c->tenant, $c, $meta);

            return $c->refresh();
        }, 3);
    }

    public function status(WhatsAppConversation $conversation, User $actor, WhatsAppConversationStatus $to): WhatsAppConversation
    {
        $allowed = ['open' => ['pending', 'closed', 'archived'], 'pending' => ['open', 'closed', 'archived'], 'closed' => ['open', 'archived'], 'archived' => ['open', 'closed']];

        return DB::transaction(function () use ($conversation, $actor, $to, $allowed) {
            $c = $this->lock($conversation);
            $from = $c->status;
            if ($from === $to) {
                return $c;
            }if (! in_array($to->value, $allowed[$from->value] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Unsupported conversation status transition.']);
            }$changes = ['status' => $to, 'last_agent_activity_at' => now(), 'closed_at' => $to === WhatsAppConversationStatus::Closed ? now() : null, 'closed_by' => $to === WhatsAppConversationStatus::Closed ? $actor->id : null];
            if ($to === WhatsAppConversationStatus::Archived) {
                $changes += ['archived_at' => now(), 'archived_by' => $actor->id];
            }if ($from === WhatsAppConversationStatus::Archived) {
                $changes += ['archived_at' => null, 'archived_by' => null];
            }$c->update($changes);
            $meta = ['previous_status' => $from->value, 'new_status' => $to->value];
            $this->activity->record($c, $actor, 'status_changed', $meta);
            $this->audit->recordDomain('whatsapp_inbox.status_changed', $actor, $c->tenant, $c, $meta);

            return $c->refresh();
        }, 3);
    }

    public function priority(WhatsAppConversation $conversation, User $actor, WhatsAppConversationPriority $to): WhatsAppConversation
    {
        return DB::transaction(function () use ($conversation, $actor, $to) {
            $c = $this->lock($conversation);
            $from = $c->priority;
            if ($from === $to) {
                return $c;
            }$c->update(['priority' => $to, 'last_agent_activity_at' => now()]);
            $meta = ['previous_priority' => $from->value, 'new_priority' => $to->value];
            $this->activity->record($c, $actor, 'priority_changed', $meta);
            $this->audit->recordDomain('whatsapp_inbox.priority_changed', $actor, $c->tenant, $c, $meta);

            return $c->refresh();
        }, 3);
    }

    public function read(WhatsAppConversation $conversation, User $actor, bool $read): WhatsAppConversation
    {
        return DB::transaction(function () use ($conversation, $actor, $read) {
            $c = $this->lock($conversation);
            if ($read && $c->unread_count === 0) {
                return $c;
            }if (! $read && $c->unread_count > 0) {
                return $c;
            }$c->update(['unread_count' => $read ? 0 : 1, 'last_read_at' => $read ? now() : $c->last_read_at, 'last_read_by' => $read ? $actor->id : $c->last_read_by, 'last_agent_activity_at' => now()]);
            $this->activity->record($c, $actor, $read ? 'marked_read' : 'marked_unread');

            return $c->refresh();
        }, 3);
    }

    private function lock(WhatsAppConversation $c): WhatsAppConversation
    {
        return WhatsAppConversation::forTenant($c->tenant_id)->with(['tenant', 'assignedUser'])->lockForUpdate()->findOrFail($c->id);
    }
}
