<?php

namespace App\Services\Inbox;

use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppConversationActivity;

final class ConversationActivityRecorder
{
    public function record(WhatsAppConversation $c, ?User $actor, string $type, array $metadata = [], ?string $subjectUuid = null): void
    {
        WhatsAppConversationActivity::create(['tenant_id' => $c->tenant_id, 'whatsapp_conversation_id' => $c->id, 'actor_id' => $actor?->id, 'activity_type' => $type, 'subject_uuid' => $subjectUuid, 'metadata' => $metadata ?: null, 'occurred_at' => now(), 'created_at' => now()]);
    }
}
