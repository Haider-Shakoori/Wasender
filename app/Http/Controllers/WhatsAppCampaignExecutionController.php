<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Http\Requests\RetryWhatsAppCampaignRecipientRequest;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipientExecution;
use App\Services\Campaigns\RetryWhatsAppCampaignRecipientService;
use App\Services\Campaigns\WhatsAppCampaignDispatchAttemptQuery;
use App\Services\Campaigns\WhatsAppCampaignExecutionQuery;
use App\Services\Campaigns\WhatsAppCampaignRecipientExecutionQuery;
use Illuminate\Http\Request;

final class WhatsAppCampaignExecutionController extends Controller
{
    public function show(WhatsAppCampaign $campaign, TenantContext $ctx, WhatsAppCampaignExecutionQuery $q)
    {
        $this->owned($campaign, $ctx);
        $this->authorize('viewExecution', $campaign);

        return view('tenant.campaigns.execution', ['campaign' => $campaign, 'execution' => $q->latest($campaign)]);
    }

    public function recipients(Request $r, WhatsAppCampaign $campaign, TenantContext $ctx, WhatsAppCampaignExecutionQuery $eq, WhatsAppCampaignRecipientExecutionQuery $q)
    {
        $this->owned($campaign, $ctx);
        $this->authorize('viewRecipientExecution', $campaign);
        $e = $eq->latest($campaign);
        abort_unless($e, 404);

        return view('tenant.campaigns.execution-recipients', ['campaign' => $campaign, 'execution' => $e, 'rows' => $q->paginate($e, $r->only(['status', 'session', 'failure_code']))]);
    }

    public function attempts(Request $r, WhatsAppCampaign $campaign, TenantContext $ctx, WhatsAppCampaignExecutionQuery $eq, WhatsAppCampaignDispatchAttemptQuery $q)
    {
        $this->owned($campaign, $ctx);
        $this->authorize('viewExecution', $campaign);
        $e = $eq->latest($campaign);
        abort_unless($e, 404);

        return view('tenant.campaigns.execution-attempts', ['campaign' => $campaign, 'execution' => $e, 'attempts' => $q->paginate($e, $r->only(['status']))]);
    }

    public function retry(RetryWhatsAppCampaignRecipientRequest $request, WhatsAppCampaign $campaign, WhatsAppCampaignRecipientExecution $recipientExecution, TenantContext $ctx, RetryWhatsAppCampaignRecipientService $service)
    {
        $this->owned($campaign, $ctx);
        $this->authorize('retryRecipient', $campaign);
        abort_unless($recipientExecution->tenant_id === $ctx->id() && $recipientExecution->whatsapp_campaign_id === $campaign->id, 404);
        $service->retry($recipientExecution);

        return back()->with('success', 'Recipient retry queued without calling Node.');
    }

    private function owned(WhatsAppCampaign $c, TenantContext $ctx): void
    {
        abort_unless($c->tenant_id === $ctx->id(), 404);
    }
}
