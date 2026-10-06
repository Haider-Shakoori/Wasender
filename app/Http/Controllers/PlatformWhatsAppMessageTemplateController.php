<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppMessageTemplate;
use App\Services\Templates\PlatformWhatsAppMessageTemplateQuery;
use Illuminate\Http\Request;

final class PlatformWhatsAppMessageTemplateController extends Controller
{
    public function index(Request $request, PlatformWhatsAppMessageTemplateQuery $query)
    {
        $templates = $query->paginate($request->only(['tenant_id', 'status', 'type', 'has_attachment', 'media_category', 'date_from', 'date_to']));

        return $request->expectsJson() ? response()->json($templates) : view('platform.message-templates.index', ['templates' => $templates]);
    }

    public function show(WhatsAppMessageTemplate $template)
    {
        $template->load(['tenant:id,uuid,name', 'creator:id,name', 'currentDraftVersion:id,whatsapp_message_template_id,uuid,version_number,status', 'currentPublishedVersion:id,whatsapp_message_template_id,uuid,version_number,status', 'versions' => fn ($q) => $q->select(['id', 'whatsapp_message_template_id', 'uuid', 'version_number', 'status', 'content_hash', 'published_by', 'published_at', 'created_at'])->with(['publisher:id,name', 'attachment:id,whatsapp_message_template_version_id,safe_name,mime_type,size_bytes,checksum_sha256,media_category'])->orderByDesc('version_number')]);

        $safe = $template->only(['uuid', 'name', 'type', 'status', 'lock_version', 'created_at', 'updated_at', 'published_at', 'archived_at']) + ['tenant' => $template->tenant, 'current_draft_version' => $template->currentDraftVersion, 'current_published_version' => $template->currentPublishedVersion];

        return request()->expectsJson() ? response()->json($safe) : view('platform.message-templates.show', ['template' => $template, 'usageCounts' => $template->usages()->selectRaw('usage_type,count(*) total')->groupBy('usage_type')->pluck('total', 'usage_type')]);
    }
}
