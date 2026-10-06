<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Http\Requests\SendWhatsAppMessageFromTemplateRequest;
use App\Services\Templates\CreateTransactionalWhatsAppMessageFromTemplateService;

final class SendWhatsAppMessageFromTemplateController extends Controller
{
    public function __invoke(SendWhatsAppMessageFromTemplateRequest $request, TenantContext $context, CreateTransactionalWhatsAppMessageFromTemplateService $service)
    {
        $message = $service->create($context->get(), $request->user(), $request->validated());

        return $request->expectsJson() ? response()->json(['uuid' => $message->uuid, 'status' => $message->status->value, 'template_uuid' => $message->template_uuid, 'template_version_uuid' => $message->template_version_uuid], 202) : redirect()->route('tenant.messages.show', $message->uuid)->with('status', 'Template message queued.');
    }
}
