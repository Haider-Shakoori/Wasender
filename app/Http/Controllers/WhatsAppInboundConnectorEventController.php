<?php

namespace App\Http\Controllers;

use App\Http\Requests\InboundWhatsAppMessageEventRequest;
use App\Services\Inbox\ProcessInboundWhatsAppMessageService;
use Illuminate\Http\JsonResponse;

final class WhatsAppInboundConnectorEventController extends Controller
{
    public function __invoke(InboundWhatsAppMessageEventRequest $request, ProcessInboundWhatsAppMessageService $service): JsonResponse
    {
        return response()->json($service->process($request->data())->toArray(), 202);
    }
}
