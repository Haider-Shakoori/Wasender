<?php

namespace App\Services\Campaigns;

use App\Contracts\TenantContext;
use App\Models\WhatsAppCampaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class WhatsAppCampaignRecipientQuery
{
    public function __construct(private TenantContext $context) {}

    public function paginate(WhatsAppCampaign $campaign, array $filters): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? '', ['display_name', 'source_type', 'prepared_at'], true) ? $filters['sort'] : 'prepared_at';

        return $campaign->recipients()->where('tenant_id', $this->context->id())->where('preparation_id', $campaign->active_preparation_id)
            ->with('execution:id,campaign_recipient_id,status')
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($x) => $x->where('display_name', 'like', '%'.addcslashes($v, '%_').'%')->orWhere('phone_normalized', 'like', '%'.preg_replace('/\D/', '', $v).'%')))
            ->when($filters['source_type'] ?? null, fn ($q, $v) => $q->where('source_type', $v))
            ->when($filters['execution_status'] ?? null, fn ($q, $v) => $q->whereHas('execution', fn ($x) => $x->where('status', $v)))
            ->orderBy($sort)->paginate(25)->withQueryString();
    }
}
