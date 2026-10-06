<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Http\Requests\ValidateWhatsAppMessageTemplateRequest;
use App\Models\WhatsAppMessageTemplate;
use App\Services\Templates\ValidateWhatsAppMessageTemplateVersionService;
use Illuminate\Http\JsonResponse;

final class ValidateWhatsAppMessageTemplateController extends Controller
{
    public function __invoke(ValidateWhatsAppMessageTemplateRequest $request, WhatsAppMessageTemplate $template, TenantContext $context, ValidateWhatsAppMessageTemplateVersionService $service): JsonResponse
    {
        abort_unless($template->tenant_id === $context->id(), 404);
        $this->authorize('update', $template);
        $version = $template->currentDraftVersion;
        abort_unless($version, 422, 'No active draft exists.');

        return response()->json($service->validate($version, $template->type));
    }
}
