<?php

namespace App\Services\Inbox;

use App\Enums\MembershipStatus;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppConversationNote;
use App\Notifications\WhatsAppConversationMentionNotification;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WhatsAppConversationNoteService
{
    public function __construct(private ConversationActivityRecorder $activity, private AuditService $audit) {}

    public function create(WhatsAppConversation $c, User $actor, string $body, array $mentionUuids): WhatsAppConversationNote
    {
        return DB::transaction(function () use ($c, $actor, $body, $mentionUuids) {
            $c = WhatsAppConversation::forTenant($c->tenant_id)->lockForUpdate()->findOrFail($c->id);
            $note = WhatsAppConversationNote::create(['tenant_id' => $c->tenant_id, 'whatsapp_conversation_id' => $c->id, 'author_id' => $actor->id, 'body' => $this->body($body)]);
            $this->syncMentions($note, $actor, $mentionUuids);
            $c->update(['last_agent_activity_at' => now()]);
            $this->activity->record($c, $actor, 'note_added', [], $note->uuid);
            $this->audit->recordDomain('whatsapp_inbox.note_created', $actor, $c->tenant, $note, ['conversation_uuid' => $c->uuid, 'note_uuid' => $note->uuid]);

            return $note->load(['author:id,uuid,name', 'mentions:id,uuid,name']);
        }, 3);
    }

    public function update(WhatsAppConversationNote $note, User $actor, string $body, array $mentions): WhatsAppConversationNote
    {
        return DB::transaction(function () use ($note, $actor, $body, $mentions) {
            $note = WhatsAppConversationNote::where('tenant_id', $note->tenant_id)->lockForUpdate()->findOrFail($note->id);
            $note->update(['body' => $this->body($body), 'edited_at' => now()]);
            $this->syncMentions($note, $actor, $mentions);
            $note->conversation()->update(['last_agent_activity_at' => now()]);
            $this->activity->record($note->conversation, $actor, 'note_updated', [], $note->uuid);
            $this->audit->recordDomain('whatsapp_inbox.note_updated', $actor, $note->conversation->tenant, $note, ['note_uuid' => $note->uuid]);

            return $note->load('mentions');
        }, 3);
    }

    public function delete(WhatsAppConversationNote $note, User $actor): void
    {
        DB::transaction(function () use ($note, $actor) {
            $note = WhatsAppConversationNote::where('tenant_id', $note->tenant_id)->lockForUpdate()->findOrFail($note->id);
            $c = $note->conversation;
            $note->delete();
            $c->update(['last_agent_activity_at' => now()]);
            $this->activity->record($c, $actor, 'note_deleted', [], $note->uuid);
            $this->audit->recordDomain('whatsapp_inbox.note_deleted', $actor, $c->tenant, $note, ['note_uuid' => $note->uuid]);
        }, 3);
    }

    private function syncMentions(WhatsAppConversationNote $note, User $actor, array $uuids): void
    {
        $uuids = array_values(array_unique(array_filter($uuids)));
        if ($uuids && ! $actor->hasTenantPermission($note->conversation->tenant, 'inbox.mention_users')) {
            throw ValidationException::withMessages(['mentions' => 'You cannot mention workspace members.']);
        }
        $members = TenantMembership::forTenant($note->tenant_id)->where('status', MembershipStatus::Active)->whereHas('user', fn ($q) => $q->whereIn('uuid', $uuids)->where('status', 'active'))->with('user')->get()->pluck('user')->reject(fn (User $u) => $u->id === $actor->id);
        if ($members->count() !== count(array_diff($uuids, [$actor->uuid]))) {
            throw ValidationException::withMessages(['mentions' => 'Every mention must be an active workspace member.']);
        }
        $existing = $note->mentions()->pluck('users.id');
        $note->mentions()->sync($members->mapWithKeys(fn (User $u) => [$u->id => ['tenant_id' => $note->tenant_id]])->all());
        foreach ($members->whereNotIn('id', $existing) as $user) {
            $user->notify(new WhatsAppConversationMentionNotification($note->conversation->uuid, $note->uuid, $actor->name, Str::limit($note->body, 120)));
            $this->activity->record($note->conversation, $actor, 'mention_created', [], $note->uuid);
        }
    }

    private function body(string $body): string
    {
        $body = trim(strip_tags($body));
        if ($body === '' || mb_strlen($body) > 5000) {
            throw ValidationException::withMessages(['body' => 'Enter a plain-text note up to 5,000 characters.']);
        }

        return $body;
    }
}
