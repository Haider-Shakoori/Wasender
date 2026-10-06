<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcessWhatsAppCampaignConnectorEventRequest;
use App\Services\Campaigns\ProcessWhatsAppCampaignConnectorEventService;
use Illuminate\Http\JsonResponse;

final class WhatsAppCampaignConnectorEventController
{
    public function __invoke(ProcessWhatsAppCampaignConnectorEventRequest $request, ProcessWhatsAppCampaignConnectorEventService $processor): JsonResponse
    {
        $duplicate = $processor->process($request->validated(), hash('sha256', $request->getContent()));

        return response()->json(['accepted' => true, 'duplicate' => $duplicate]);
    }
}
