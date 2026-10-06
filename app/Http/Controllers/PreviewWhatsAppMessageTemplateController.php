<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Http\Requests\PreviewWhatsAppMessageTemplateRequest;
use App\Models\WhatsAppMessageTemplate;
use App\Services\Templates\PreviewWhatsAppMessageTemplateService;
use Illuminate\Http\JsonResponse;

final class PreviewWhatsAppMessageTemplateController extends Controller
{
    public function __invoke(PreviewWhatsAppMessageTemplateRequest $request, WhatsAppMessageTemplate $template, TenantContext $context, PreviewWhatsAppMessageTemplateService $service): JsonResponse
    {
        abort_unless($template->tenant_id === $context->id(), 404);
        $this->authorize('view', $template);
        $version = $template->currentDraftVersion ?: $template->currentPublishedVersion;
        abort_unless($version, 422, 'No previewable version exists.');
        $version->load('attachment')->setRelation('template', $template);

        return response()->json($service->preview($version, $request->validated('values', []), $request->validated('timezone', 'UTC')));
    }
}
