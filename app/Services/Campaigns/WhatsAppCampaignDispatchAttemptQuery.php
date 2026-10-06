<?php

namespace App\Services\Campaigns;

use App\Contracts\TenantContext;
use App\Models\WhatsAppCampaignDispatchAttempt;
use App\Models\WhatsAppCampaignExecution;

final class WhatsAppCampaignDispatchAttemptQuery
{
    public function __construct(private TenantContext $context) {}

    public function paginate(WhatsAppCampaignExecution $execution, array $f)
    {
        return $execution->attempts()->where('tenant_id', $this->context->id())->with(['session', 'recipientExecution:id,uuid'])->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->orderByDesc('id')->paginate(25)->withQueryString();
    }

    public function platform(array $filters)
    {
        return WhatsAppCampaignDispatchAttempt::with(['session:id,uuid,name', 'recipientExecution:id,uuid', 'recipientExecution.execution:id,uuid', 'recipientExecution.execution.campaign:id,uuid,name', 'recipientExecution.execution.tenant:id,uuid,name'])
            ->when($filters['tenant_id'] ?? null, fn ($q, $v) => $q->where('tenant_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['failure_class'] ?? null, fn ($q, $v) => $q->where('failure_class', $v))
            ->when($filters['failure_code'] ?? null, fn ($q, $v) => $q->where('failure_code', $v))
            ->latest('updated_at')->paginate(30)->withQueryString();
    }
}
