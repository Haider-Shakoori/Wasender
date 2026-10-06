<?php

namespace App\Services\Campaigns;

use App\Contracts\TenantContext;
use App\Models\WhatsAppCampaignExecution;

final class WhatsAppCampaignRecipientExecutionQuery
{
    public function __construct(private TenantContext $context) {}

    public function paginate(WhatsAppCampaignExecution $execution, array $f)
    {
        return $execution->recipients()->where('tenant_id', $this->context->id())->with(['recipient', 'session'])
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['session'] ?? null, fn ($q, $v) => $q->where('session_id', $v))
            ->when($f['failure_code'] ?? null, fn ($q, $v) => $q->where('failure_code', $v))
            ->orderBy('id')->paginate(25)->withQueryString();
    }
}
