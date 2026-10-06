<?php

namespace App\Services\Inbox;

use App\Contracts\TenantContext;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppInboxMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class WhatsAppInboxMessageQuery
{
    public function __construct(private TenantContext $context) {}

    public function paginate(WhatsAppConversation $conversation, array $filters): LengthAwarePaginator
    {
        abort_unless($conversation->tenant_id === $this->context->id(), 404);

        return WhatsAppInboxMessage::query()->where('tenant_id', $this->context->id())->where('whatsapp_conversation_id', $conversation->id)->with(['replyToMessage:id,uuid,message_preview', 'outboundMessage:id,uuid,status,failure_retryable,failure_code'])
            ->when($filters['direction'] ?? null, fn ($q, $v) => $q->where('direction', $v))->when($filters['message_type'] ?? null, fn ($q, $v) => $q->where('message_type', $v))
            ->when($filters['before'] ?? null, fn ($q, $v) => $q->where('occurred_at', '<', $v))->when($filters['after'] ?? null, fn ($q, $v) => $q->where('occurred_at', '>', $v))
            ->orderByDesc('occurred_at')->orderByDesc('id')->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 50))))->withQueryString();
    }
}
