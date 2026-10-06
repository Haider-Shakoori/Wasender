<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Models\WhatsAppCampaign;
use App\Services\Campaigns\WhatsAppCampaignExclusionQuery;
use App\Services\Campaigns\WhatsAppCampaignRecipientQuery;
use Illuminate\Http\Request;

final class WhatsAppCampaignRecipientController extends Controller
{
    public function recipients(Request $request, WhatsAppCampaign $campaign, TenantContext $context, WhatsAppCampaignRecipientQuery $query)
    {
        abort_unless($campaign->tenant_id === $context->id(), 404);
        $this->authorize('viewRecipients', $campaign);

        return view('tenant.campaigns.recipients', ['campaign' => $campaign, 'recipients' => $query->paginate($campaign, $request->only(['search', 'source_type', 'execution_status', 'sort']))]);
    }

    public function exclusions(Request $request, WhatsAppCampaign $campaign, TenantContext $context, WhatsAppCampaignExclusionQuery $query)
    {
        abort_unless($campaign->tenant_id === $context->id(), 404);
        $this->authorize('viewExclusions', $campaign);

        return view('tenant.campaigns.exclusions', ['campaign' => $campaign, 'exclusions' => $query->paginate($campaign, $request->only(['reason', 'source_type']))]);
    }
}
