<?php

namespace App\Services;

use App\Contracts\TenantContext;
use App\Models\WhatsAppMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class WhatsAppMessageQuery
{
    public function __construct(private TenantContext $context) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        return WhatsAppMessage::where('tenant_id', $this->context->id())->with(['session:id,uuid,name', 'attachment:id,whatsapp_message_id,safe_name'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function ($q, $search): void {
                $digits = preg_replace('/\D/', '', (string) $search);
                $q->where(fn ($inner) => $inner->where('uuid', $search)->orWhere('recipient_normalized', 'like', "%{$digits}%"));
            })->latest()->paginate(20)->withQueryString();
    }

    public function find(string $uuid): WhatsAppMessage
    {
        return WhatsAppMessage::where('tenant_id', $this->context->id())->with(['session:id,uuid,name,status', 'attachment', 'events' => fn ($q) => $q->latest('occurred_at')->limit(50), 'attempts' => fn ($q) => $q->latest('attempt_number')])->where('uuid', $uuid)->firstOrFail();
    }
}
