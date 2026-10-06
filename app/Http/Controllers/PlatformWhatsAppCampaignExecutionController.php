<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppCampaignExecution;
use App\Services\Campaigns\PlatformWhatsAppCampaignExecutionQuery;
use Illuminate\Http\Request;

final class PlatformWhatsAppCampaignExecutionController extends Controller
{
    public function index(Request $r, PlatformWhatsAppCampaignExecutionQuery $q)
    {
        return view('platform.campaign-executions.index', ['executions' => $q->paginate($r->only(['status', 'tenant_id', 'has_failures', 'has_unknown']))]);
    }

    public function show(WhatsAppCampaignExecution $execution)
    {
        return view('platform.campaign-executions.show', ['execution' => $execution->load(['tenant:id,uuid,name', 'campaign:id,uuid,name,payload_hash', 'preparation:id,uuid', 'reservation'])]);
    }
}
