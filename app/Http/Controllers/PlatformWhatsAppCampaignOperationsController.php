<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppCampaignExecution;
use App\Services\Campaigns\ReconcileWhatsAppCampaignExecutionService;
use App\Services\Campaigns\WhatsAppCampaignConnectorHealthQuery;
use App\Services\Campaigns\WhatsAppCampaignDispatchAttemptQuery;
use App\Services\Campaigns\WhatsAppCampaignPreparationQuery;
use App\Services\PlatformAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

final class PlatformWhatsAppCampaignOperationsController extends Controller
{
    public function preparations(Request $request, WhatsAppCampaignPreparationQuery $query): View
    {
        return view('platform.campaign-preparations.index', ['preparations' => $query->platform($request->only(['tenant_id', 'status', 'failure_code']))]);
    }

    public function attempts(Request $request, WhatsAppCampaignDispatchAttemptQuery $query): View
    {
        return view('platform.campaign-attempts.index', ['attempts' => $query->platform($request->only(['tenant_id', 'status', 'failure_class', 'failure_code']))]);
    }

    public function connector(WhatsAppCampaignConnectorHealthQuery $query): View
    {
        return view('platform.campaign-connector.show', ['health' => $query->get()]);
    }

    public function reconcileExecution(Request $request, WhatsAppCampaignExecution $execution, ReconcileWhatsAppCampaignExecutionService $service, PlatformAuditService $audit): RedirectResponse
    {
        $result = $service->reconcile($execution);
        $audit->record('whatsapp_campaign.execution_reconciled', $request->user(), $execution, ['execution_uuid' => $execution->uuid] + $result);

        return back()->with('status', 'Execution counters and stale claims reconciled through the canonical service.');
    }

    public function reconcileTransport(Request $request, PlatformAuditService $audit): RedirectResponse
    {
        Artisan::queue('whatsapp-campaigns:reconcile-transport', ['--limit' => min(25, config('whatsapp_campaign_transport.reconcile_batch'))]);
        $audit->record('whatsapp_campaign.transport_reconciliation_requested', $request->user(), null, ['limit' => min(25, config('whatsapp_campaign_transport.reconcile_batch'))]);

        return back()->with('status', 'Transport reconciliation queued. No resend or direct status mutation was forced.');
    }

    public function refreshConnector(Request $request, PlatformAuditService $audit): RedirectResponse
    {
        $audit->record('whatsapp_campaign.connector_status_refreshed', $request->user());

        return back()->with('status', 'Connector health refreshed.');
    }
}
