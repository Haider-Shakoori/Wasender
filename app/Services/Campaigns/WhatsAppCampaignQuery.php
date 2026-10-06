<?php

namespace App\Services\Campaigns;

use App\Contracts\TenantContext;
use App\Models\WhatsAppCampaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class WhatsAppCampaignQuery
{
    public function __construct(private TenantContext $context) {}

    public function paginate(array $f): LengthAwarePaginator
    {
        $sort = in_array($f['sort'] ?? '', ['name', 'status', 'created_at', 'updated_at', 'scheduled_at_utc'], true) ? $f['sort'] : 'updated_at';

        return WhatsAppCampaign::forTenant($this->context->id())->with('creator')
            ->when($f['search'] ?? null, fn ($q, $v) => $q->where('name', 'like', '%'.addcslashes($v, '%_').'%'))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['message_type'] ?? null, fn ($q, $v) => $q->where('message_type', $v))
            ->when($f['audience_type'] ?? null, fn ($q, $v) => $q->where('audience_type', $v))
            ->when($f['schedule_type'] ?? null, fn ($q, $v) => $q->where('schedule_type', $v))
            ->when($f['needs_attention'] ?? null, fn ($q) => $q->where(fn ($x) => $x->whereIn('status', ['needs_attention', 'failed', 'completed_with_errors'])->orWhere('failed_recipient_count', '>', 0)))
            ->when($f['date_from'] ?? null, fn ($q, $v) => $q->whereDate('updated_at', '>=', $v))
            ->when($f['date_to'] ?? null, fn ($q, $v) => $q->whereDate('updated_at', '<=', $v))
            ->when(empty($f['archived']), fn ($q) => $q->where('status', '!=', 'archived'))
            ->orderBy($sort, ($f['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc')->paginate(20)->withQueryString();
    }
}
