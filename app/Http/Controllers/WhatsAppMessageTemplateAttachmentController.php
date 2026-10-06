<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Data\Templates\RemoveWhatsAppMessageTemplateAttachmentData;
use App\Data\Templates\StoreWhatsAppMessageTemplateAttachmentData;
use App\Http\Requests\RemoveWhatsAppMessageTemplateAttachmentRequest;
use App\Http\Requests\StoreWhatsAppMessageTemplateAttachmentRequest;
use App\Models\WhatsAppMessageTemplate;
use App\Services\Templates\RemoveWhatsAppMessageTemplateAttachmentService;
use App\Services\Templates\StoreWhatsAppMessageTemplateAttachmentService;

final class WhatsAppMessageTemplateAttachmentController extends Controller
{
    public function store(StoreWhatsAppMessageTemplateAttachmentRequest $request, WhatsAppMessageTemplate $template, TenantContext $context, StoreWhatsAppMessageTemplateAttachmentService $service)
    {
        abort_unless($template->tenant_id === $context->id(), 404);
        $this->authorize('manageMedia', $template);
        $attachment = $service->store($template, new StoreWhatsAppMessageTemplateAttachmentData($request->file('attachment'), $request->integer('expected_version')), $request->user());

        return $request->expectsJson() ? response()->json($attachment->only(['uuid', 'safe_name', 'mime_type', 'size_bytes', 'checksum_sha256', 'media_category']), 201) : back()->with('status', 'Draft media saved.');
    }

    public function destroy(RemoveWhatsAppMessageTemplateAttachmentRequest $request, WhatsAppMessageTemplate $template, TenantContext $context, RemoveWhatsAppMessageTemplateAttachmentService $service)
    {
        abort_unless($template->tenant_id === $context->id(), 404);
        $this->authorize('manageMedia', $template);
        $service->remove($template, new RemoveWhatsAppMessageTemplateAttachmentData($request->integer('expected_version')), $request->user());

        return $request->expectsJson() ? response()->json(null, 204) : back()->with('status', 'Draft media removed.');
    }
}
