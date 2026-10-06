<?php

namespace App\Services\Campaigns;

use App\Contracts\TenantContext;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignPreparation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class WhatsAppCampaignPreparationQuery
{
    public function __construct(private TenantContext $context) {}

    public function forCampaign(WhatsAppCampaign $campaign): LengthAwarePaginator
    {
        return WhatsAppCampaignPreparation::where('tenant_id', $this->context->id())->where('whatsapp_campaign_id', $campaign->id)->latest('id')->paginate(20);
    }

    public function platform(array $filters): LengthAwarePaginator
    {
        return WhatsAppCampaignPreparation::with(['tenant:id,uuid,name', 'campaign:id,uuid,name,payload_hash'])->when($filters['tenant_id'] ?? null, fn ($q, $v) => $q->where('tenant_id', $v))->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($filters['failure_code'] ?? null, fn ($q, $v) => $q->where('failure_code', $v))->latest('id')->paginate(30)->withQueryString();
    }
}
