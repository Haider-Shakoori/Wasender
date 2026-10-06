<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplyWhatsAppMessageTemplateRequest;
use App\Models\WhatsAppCampaign;
use App\Services\Campaigns\ApplyWhatsAppMessageTemplateToCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WhatsAppCampaignTemplateController extends Controller
{
    public function apply(ApplyWhatsAppMessageTemplateRequest $request, WhatsAppCampaign $campaign, ApplyWhatsAppMessageTemplateToCampaignService $service): JsonResponse
    {
        $campaign = $service->apply($campaign, $request->string('template_uuid')->toString(), $request->input('values', []), $request->user(), $request->integer('expected_version'));

        return $request->expectsJson() ? response()->json($campaign) : back()->with('status', 'Published template applied to campaign.');
    }

    public function detach(Request $request, WhatsAppCampaign $campaign, ApplyWhatsAppMessageTemplateToCampaignService $service): JsonResponse
    {
        $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);

        $campaign = $service->detach($campaign, $request->user(), $request->integer('expected_version'));

        return $request->expectsJson() ? response()->json($campaign) : back()->with('status', 'Template detached; current campaign content was preserved.');
    }
}
