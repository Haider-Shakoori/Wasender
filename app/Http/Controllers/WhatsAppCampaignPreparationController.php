<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Data\Campaigns\PrepareWhatsAppCampaignData;
use App\Http\Requests\CancelWhatsAppCampaignPreparationRequest;
use App\Http\Requests\EstimateWhatsAppCampaignAudienceRequest;
use App\Http\Requests\InvalidateWhatsAppCampaignPreparationRequest;
use App\Http\Requests\PrepareWhatsAppCampaignRequest;
use App\Models\WhatsAppCampaign;
use App\Services\Campaigns\CampaignAudienceEstimateService;
use App\Services\Campaigns\CancelWhatsAppCampaignPreparationService;
use App\Services\Campaigns\InvalidateWhatsAppCampaignPreparationService;
use App\Services\Campaigns\PrepareWhatsAppCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class WhatsAppCampaignPreparationController extends Controller
{
    public function prepare(PrepareWhatsAppCampaignRequest $request, WhatsAppCampaign $campaign, TenantContext $context, PrepareWhatsAppCampaignService $service): RedirectResponse
    {
        $this->owned($campaign, $context);
        $this->authorize('prepare', $campaign);
        $service->prepare($campaign, new PrepareWhatsAppCampaignData($request->string('idempotency_token'), $request->integer('expected_version')), $request->user());

        return back()->with('success', 'Recipient preparation queued. No messages are being sent.');
    }

    public function estimate(EstimateWhatsAppCampaignAudienceRequest $request, WhatsAppCampaign $campaign, TenantContext $context, CampaignAudienceEstimateService $service): JsonResponse
    {
        $this->owned($campaign, $context);
        $this->authorize('view', $campaign);
        $estimate = $service->estimate($campaign);

        return response()->json(['candidate_count' => $estimate->candidateCount, 'estimated_eligible_count' => $estimate->estimatedEligibleCount, 'authoritative' => false, 'warnings' => $estimate->warnings, 'calculated_at' => $estimate->calculatedAt->toIso8601String()]);
    }

    public function cancel(CancelWhatsAppCampaignPreparationRequest $request, WhatsAppCampaign $campaign, TenantContext $context, CancelWhatsAppCampaignPreparationService $service): RedirectResponse
    {
        $this->owned($campaign, $context);
        $this->authorize('cancelPreparation', $campaign);
        $service->cancel($campaign, $request->user());

        return back()->with('success', 'Recipient preparation cancelled. No messages were affected.');
    }

    public function invalidate(InvalidateWhatsAppCampaignPreparationRequest $request, WhatsAppCampaign $campaign, TenantContext $context, InvalidateWhatsAppCampaignPreparationService $service): RedirectResponse
    {
        $this->owned($campaign, $context);
        $this->authorize('invalidatePreparation', $campaign);
        $service->invalidate($campaign, $request->user(), $request->integer('expected_version'));

        return back()->with('success', 'Prepared recipient snapshot invalidated.');
    }

    private function owned(WhatsAppCampaign $campaign, TenantContext $context): void
    {
        abort_unless($campaign->tenant_id === $context->id(), 404);
    }
}
