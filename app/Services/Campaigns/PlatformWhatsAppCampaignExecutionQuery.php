<?php

namespace App\Services\Campaigns;

use App\Models\WhatsAppCampaignExecution;

final class PlatformWhatsAppCampaignExecutionQuery
{
    public function paginate(array $f)
    {
        return WhatsAppCampaignExecution::with(['tenant:id,uuid,name', 'campaign:id,uuid,name', 'preparation:id,uuid'])
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['tenant_id'] ?? null, fn ($q, $v) => $q->where('tenant_id', $v))
            ->when($f['has_failures'] ?? null, fn ($q) => $q->where('failed_recipients', '>', 0))
            ->when($f['has_unknown'] ?? null, fn ($q) => $q->where('unknown_recipients', '>', 0))
            ->latest('id')->paginate(30)->withQueryString();
    }
}
