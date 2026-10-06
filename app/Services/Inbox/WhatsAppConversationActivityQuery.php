<?php

namespace App\Services\Inbox;

use App\Contracts\TenantContext;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppConversationActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class WhatsAppConversationActivityQuery
{
    public function __construct(private TenantContext $context) {}

    public function paginate(WhatsAppConversation $c, int $perPage = 50): LengthAwarePaginator
    {
        abort_unless($c->tenant_id === $this->context->id(), 404);

        return WhatsAppConversationActivity::where('tenant_id', $this->context->id())->where('whatsapp_conversation_id', $c->id)->with('actor:id,uuid,name')->latest('occurred_at')->paginate(min(100, max(1, $perPage)));
    }
}
