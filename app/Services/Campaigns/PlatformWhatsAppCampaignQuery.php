<?php

namespace App\Services\Campaigns;

use App\Models\WhatsAppCampaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PlatformWhatsAppCampaignQuery
{
    public function paginate(array $f): LengthAwarePaginator
    {
        return WhatsAppCampaign::query()->with(['tenant:id,uuid,name', 'activeExecution:id,unknown_recipients'])
            ->when($f['tenant_id'] ?? null, fn ($q, $v) => $q->where('tenant_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['has_failures'] ?? null, fn ($q) => $q->where('failed_recipient_count', '>', 0))
            ->when($f['has_unknown'] ?? null, fn ($q) => $q->whereHas('activeExecution', fn ($x) => $x->where('unknown_recipients', '>', 0)))
            ->when($f['date_from'] ?? null, fn ($q, $v) => $q->whereDate('updated_at', '>=', $v))
            ->when($f['date_to'] ?? null, fn ($q, $v) => $q->whereDate('updated_at', '<=', $v))
            ->latest('updated_at')->paginate(30)->withQueryString();
    }
}
