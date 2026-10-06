<?php

namespace App\Services\Campaigns;

use App\Contracts\TenantContext;
use App\Models\WhatsAppCampaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class WhatsAppCampaignExclusionQuery
{
    public function __construct(private TenantContext $context) {}

    public function paginate(WhatsAppCampaign $campaign, array $filters): LengthAwarePaginator
    {
        return $campaign->exclusions()->where('tenant_id', $this->context->id())->where('preparation_id', $campaign->active_preparation_id)
            ->when($filters['reason'] ?? null, fn ($q, $v) => $q->where('reason_code', $v))
            ->when($filters['source_type'] ?? null, fn ($q, $v) => $q->where('source_type', $v))
            ->orderByDesc('created_at')->paginate(25)->withQueryString();
    }
}
