<?php

namespace App\Services\Templates;

use App\Models\WhatsAppMessageTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PlatformWhatsAppMessageTemplateQuery
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return WhatsAppMessageTemplate::with(['tenant:id,uuid,name', 'category:id,uuid,name', 'currentDraftVersion:id,whatsapp_message_template_id,version_number', 'currentPublishedVersion:id,whatsapp_message_template_id,version_number'])
            ->when($filters['tenant_id'] ?? null, fn ($q, $v) => $q->where('tenant_id', $v))->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when(array_key_exists('has_attachment', $filters), fn ($q) => filter_var($filters['has_attachment'], FILTER_VALIDATE_BOOL) ? $q->whereHas('versions.attachment') : $q->whereDoesntHave('versions.attachment'))
            ->when($filters['media_category'] ?? null, fn ($q, $v) => $q->whereHas('versions.attachment', fn ($x) => $x->where('media_category', $v)))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('updated_at', '<=', $v))
            ->latest('updated_at')->paginate(25)->withQueryString();
    }
}
