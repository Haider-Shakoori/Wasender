<?php

namespace App\Services\Templates;

use App\Contracts\TenantContext;
use App\Models\WhatsAppMessageTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class WhatsAppMessageTemplateQuery
{
    public function __construct(private TenantContext $context) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? '', ['name', 'status', 'type', 'updated_at'], true) ? $filters['sort'] : 'updated_at';

        return WhatsAppMessageTemplate::forTenant($this->context->id())->with(['category:id,uuid,name,slug', 'labels:id,uuid,name,slug,color', 'currentDraftVersion:id,whatsapp_message_template_id,uuid,version_number,status', 'currentDraftVersion.attachment:id,whatsapp_message_template_version_id,media_category', 'currentPublishedVersion:id,whatsapp_message_template_id,uuid,version_number,status', 'currentPublishedVersion.attachment:id,whatsapp_message_template_version_id,media_category'])
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('name', 'like', '%'.addcslashes($v, '%_').'%'))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('updated_at', '>=', $v))->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('updated_at', '<=', $v))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->whereHas('category', fn ($x) => $x->where('uuid', $v)))
            ->when($filters['label'] ?? null, fn ($q, $v) => $q->whereHas('labels', fn ($x) => $x->where('uuid', $v)))
            ->when(array_key_exists('has_attachment', $filters), fn ($q) => filter_var($filters['has_attachment'], FILTER_VALIDATE_BOOL) ? $q->whereHas('versions.attachment') : $q->whereDoesntHave('versions.attachment'))
            ->when($filters['published_only'] ?? null, fn ($q) => $q->whereNotNull('current_published_version_id'))
            ->when($filters['draft_only'] ?? null, fn ($q) => $q->whereNotNull('current_draft_version_id'))
            ->when(empty($filters['archived']), fn ($q) => $q->where('status', '!=', 'archived'))
            ->orderBy($sort, ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc')->paginate(20)->withQueryString();
    }
}
