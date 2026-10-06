<?php

namespace App\Services\Inbox;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppSavedReply;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

final class WhatsAppSavedReplyService
{
    public function __construct(private AuditService $audit) {}

    public function save(Tenant $tenant, User $actor, array $data, ?WhatsAppSavedReply $reply = null): WhatsAppSavedReply
    {
        return DB::transaction(function () use ($tenant, $actor, $data, $reply) {
            $reply = $reply ? WhatsAppSavedReply::forTenant($tenant->id)->lockForUpdate()->findOrFail($reply->id) : new WhatsAppSavedReply(['tenant_id' => $tenant->id, 'created_by' => $actor->id]);
            $reply->fill(['name' => trim($data['name']), 'shortcut' => filled($data['shortcut'] ?? null) ? strtolower(trim($data['shortcut'])) : null, 'body' => trim(strip_tags($data['body'])), 'is_active' => true, 'updated_by' => $actor->id])->save();
            $this->audit->recordDomain($reply->wasRecentlyCreated ? 'whatsapp_inbox.saved_reply_created' : 'whatsapp_inbox.saved_reply_updated', $actor, $tenant, $reply, ['saved_reply_uuid' => $reply->uuid]);

            return $reply;
        }, 3);
    }

    public function archive(Tenant $tenant, User $actor, WhatsAppSavedReply $reply, bool $restore = false): WhatsAppSavedReply
    {
        $reply = WhatsAppSavedReply::withTrashed()->where('tenant_id', $tenant->id)->findOrFail($reply->id);
        $restore ? $reply->restore() : $reply->delete();
        $reply->update(['is_active' => $restore, 'updated_by' => $actor->id]);
        $this->audit->recordDomain($restore ? 'whatsapp_inbox.saved_reply_restored' : 'whatsapp_inbox.saved_reply_archived', $actor, $tenant, $reply, ['saved_reply_uuid' => $reply->uuid]);

        return $reply;
    }
}
