<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppCampaign;
use App\Services\Campaigns\PlatformWhatsAppCampaignQuery;
use Illuminate\Http\Request;

final class PlatformWhatsAppCampaignController extends Controller
{
    public function index(Request $r, PlatformWhatsAppCampaignQuery $q)
    {
        return view('platform.campaigns.index', ['campaigns' => $q->paginate($r->only(['tenant_id', 'status', 'has_failures', 'has_unknown', 'date_from', 'date_to']))]);
    }

    public function show(WhatsAppCampaign $campaign)
    {
        return view('platform.campaigns.show', ['campaign' => $campaign->load(['tenant:id,uuid,name', 'preparations' => fn ($query) => $query->latest()->limit(10), 'activePreparation', 'activeExecution', 'sessionSelections.session:id,uuid,name,status'])]);
    }
}
