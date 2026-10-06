<?php

namespace App\Services\Inbox;

use App\Contracts\TenantContext;
use App\Models\WhatsAppConversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class WhatsAppInboxConversationQuery
{
    public function __construct(private TenantContext $context) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? '', ['last_message_at', 'created_at', 'unread_count'], true) ? $filters['sort'] : 'last_message_at';

        return WhatsAppConversation::forTenant($this->context->id())->with(['contact:id,uuid,display_name,first_name,last_name,phone_normalized', 'session:id,uuid,name', 'assignedUser:id,uuid,name', 'labels:id,uuid,name,color'])
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('normalized_phone', 'like', '%'.preg_replace('/[^0-9+]/', '', $v).'%')->orWhereHas('contact', fn ($q) => $q->where('display_name', 'like', '%'.$v.'%')->orWhere('first_name', 'like', '%'.$v.'%')->orWhere('last_name', 'like', '%'.$v.'%'))))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($filters['session_uuid'] ?? null, fn ($q, $v) => $q->whereHas('session', fn ($q) => $q->where('uuid', $v)))
            ->when(array_key_exists('unread', $filters), fn ($q) => $filters['unread'] ? $q->where('unread_count', '>', 0) : $q->where('unread_count', 0))
            ->when(($filters['contact'] ?? null) === 'known', fn ($q) => $q->whereNotNull('contact_id'))->when(($filters['contact'] ?? null) === 'unknown', fn ($q) => $q->whereNull('contact_id'))
            ->when($filters['assigned_user_uuid'] ?? null, fn ($q, $v) => $q->whereHas('assignedUser', fn ($q) => $q->where('uuid', $v)))
            ->when(($filters['assignment'] ?? null) === 'unassigned', fn ($q) => $q->whereNull('assigned_user_id'))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($filters['label_uuid'] ?? null, fn ($q, $v) => $q->whereHas('labels', fn ($q) => $q->where('whatsapp_conversation_labels.uuid', $v)))
            ->orderByDesc($sort)->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 25))))->withQueryString();
    }

    public function find(string $uuid): WhatsAppConversation
    {
        return WhatsAppConversation::forTenant($this->context->id())->with(['contact', 'session', 'lastMessage'])->where('uuid', $uuid)->firstOrFail();
    }
}
