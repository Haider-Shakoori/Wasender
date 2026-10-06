<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Data\Templates\ArchiveWhatsAppMessageTemplateData;
use App\Data\Templates\CreateDraftFromPublishedTemplateData;
use App\Data\Templates\CreateWhatsAppMessageTemplateData;
use App\Data\Templates\DuplicateWhatsAppMessageTemplateData;
use App\Data\Templates\PublishWhatsAppMessageTemplateData;
use App\Data\Templates\RestoreWhatsAppMessageTemplateData;
use App\Data\Templates\UpdateWhatsAppMessageTemplateDraftData;
use App\Http\Requests\ArchiveWhatsAppMessageTemplateRequest;
use App\Http\Requests\DuplicateWhatsAppMessageTemplateRequest;
use App\Http\Requests\PublishWhatsAppMessageTemplateRequest;
use App\Http\Requests\RestoreWhatsAppMessageTemplateRequest;
use App\Http\Requests\StoreWhatsAppMessageTemplateRequest;
use App\Http\Requests\UpdateWhatsAppMessageTemplateRequest;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppMessageTemplateCategory;
use App\Models\WhatsAppMessageTemplateLabel;
use App\Services\Templates\ArchiveWhatsAppMessageTemplateService;
use App\Services\Templates\CreateWhatsAppMessageTemplateDraftVersionService;
use App\Services\Templates\CreateWhatsAppMessageTemplateService;
use App\Services\Templates\DuplicateWhatsAppMessageTemplateService;
use App\Services\Templates\PublishWhatsAppMessageTemplateService;
use App\Services\Templates\RestoreWhatsAppMessageTemplateService;
use App\Services\Templates\UpdateWhatsAppMessageTemplateDraftService;
use App\Services\Templates\WhatsAppMessageTemplateQuery;
use App\Services\Templates\WhatsAppTemplateVariableRegistry;
use Illuminate\Http\Request;

final class WhatsAppMessageTemplateController extends Controller
{
    public function index(Request $request, WhatsAppMessageTemplateQuery $query)
    {
        $this->authorize('viewAny', WhatsAppMessageTemplate::class);

        $templates = $query->paginate($request->only(['search', 'status', 'type', 'category', 'label', 'has_attachment', 'published_only', 'draft_only', 'archived', 'date_from', 'date_to', 'sort', 'direction']));
        if ($request->expectsJson()) {
            return response()->json($templates);
        }

        return view('tenant.message-templates.index', ['templates' => $templates, ...$this->options(app(TenantContext::class)->id())]);
    }

    public function create(TenantContext $context, WhatsAppTemplateVariableRegistry $registry)
    {
        $this->authorize('create', WhatsAppMessageTemplate::class);

        return view('tenant.message-templates.form', [...$this->options($context->id()), 'variables' => $registry->all()]);
    }

    public function store(StoreWhatsAppMessageTemplateRequest $request, CreateWhatsAppMessageTemplateService $service)
    {
        $this->authorize('create', WhatsAppMessageTemplate::class);

        $template = $service->create(CreateWhatsAppMessageTemplateData::from($request->validated()), $request->user());

        return $request->expectsJson() ? response()->json($template, 201) : redirect()->route('tenant.message-templates.edit', $template)->with('status', 'Template draft created.');
    }

    public function show(WhatsAppMessageTemplate $template, TenantContext $context)
    {
        $this->owned($template, $context);
        $this->authorize('view', $template);

        $template->load(['category', 'labels', 'currentDraftVersion.attachment', 'currentPublishedVersion.attachment', 'versions' => fn ($q) => $q->select(['id', 'whatsapp_message_template_id', 'uuid', 'version_number', 'status', 'content_hash', 'created_by', 'published_by', 'created_at', 'published_at'])->with(['publisher:id,name'])->orderByDesc('version_number'), 'usages' => fn ($q) => $q->latest()->limit(10)]);

        return request()->expectsJson() ? response()->json($template) : view('tenant.message-templates.show', ['template' => $template, 'usageCounts' => $template->usages()->selectRaw('usage_type, count(*) total')->groupBy('usage_type')->pluck('total', 'usage_type'), ...$this->options($context->id())]);
    }

    public function edit(WhatsAppMessageTemplate $template, TenantContext $context, WhatsAppTemplateVariableRegistry $registry)
    {
        $this->owned($template, $context);
        $this->authorize('update', $template);
        $template->load(['currentDraftVersion.attachment', 'category', 'labels']);

        return view('tenant.message-templates.form', [...$this->options($context->id()), 'variables' => $registry->all(), 'template' => $template]);
    }

    public function update(UpdateWhatsAppMessageTemplateRequest $request, WhatsAppMessageTemplate $template, TenantContext $context, UpdateWhatsAppMessageTemplateDraftService $service)
    {
        $this->owned($template, $context);
        $this->authorize('update', $template);

        $updated = $service->update($template, UpdateWhatsAppMessageTemplateDraftData::from($request->validated()), $request->user());

        return $request->expectsJson() ? response()->json($updated) : redirect()->route('tenant.message-templates.show', $template)->with('status', 'Template draft updated.');
    }

    public function draft(ArchiveWhatsAppMessageTemplateRequest $request, WhatsAppMessageTemplate $template, TenantContext $context, CreateWhatsAppMessageTemplateDraftVersionService $service): JsonResponse
    {
        $this->owned($template, $context);
        $this->authorize('update', $template);

        $version = $service->create($template, CreateDraftFromPublishedTemplateData::from($request->validated()), $request->user());

        return $request->expectsJson() ? response()->json($version, 201) : back()->with('status', 'New draft version created.');
    }

    public function publish(PublishWhatsAppMessageTemplateRequest $request, WhatsAppMessageTemplate $template, TenantContext $context, PublishWhatsAppMessageTemplateService $service): JsonResponse
    {
        $this->owned($template, $context);
        $this->authorize('publish', $template);

        $version = $service->publish($template, PublishWhatsAppMessageTemplateData::from($request->validated()), $request->user());

        return $request->expectsJson() ? response()->json($version) : back()->with('status', 'Template published.');
    }

    public function duplicate(DuplicateWhatsAppMessageTemplateRequest $request, WhatsAppMessageTemplate $template, TenantContext $context, DuplicateWhatsAppMessageTemplateService $service): JsonResponse
    {
        $this->owned($template, $context);
        $this->authorize('duplicate', $template);

        $copy = $service->duplicate($template, DuplicateWhatsAppMessageTemplateData::from($request->validated()), $request->user());

        return $request->expectsJson() ? response()->json($copy, 201) : redirect()->route('tenant.message-templates.edit', $copy)->with('status', 'Template duplicated.');
    }

    public function archive(ArchiveWhatsAppMessageTemplateRequest $request, WhatsAppMessageTemplate $template, TenantContext $context, ArchiveWhatsAppMessageTemplateService $service): JsonResponse
    {
        $this->owned($template, $context);
        $this->authorize('archive', $template);

        $archived = $service->archive($template, ArchiveWhatsAppMessageTemplateData::from($request->validated()), $request->user());

        return $request->expectsJson() ? response()->json($archived) : back()->with('status', 'Template archived. Existing snapshots are unchanged.');
    }

    public function restore(RestoreWhatsAppMessageTemplateRequest $request, WhatsAppMessageTemplate $template, TenantContext $context, RestoreWhatsAppMessageTemplateService $service): JsonResponse
    {
        $this->owned($template, $context);
        $this->authorize('restore', $template);

        $restored = $service->restore($template, RestoreWhatsAppMessageTemplateData::from($request->validated()), $request->user());

        return $request->expectsJson() ? response()->json($restored) : back()->with('status', 'Template restored.');
    }

    private function owned(WhatsAppMessageTemplate $template, TenantContext $context): void
    {
        abort_unless($template->tenant_id === $context->id(), 404);
    }

    private function options(int $tenant): array
    {
        return ['categories' => WhatsAppMessageTemplateCategory::forTenant($tenant)->where('is_active', true)->orderBy('name')->get(['id', 'uuid', 'name']), 'labels' => WhatsAppMessageTemplateLabel::forTenant($tenant)->where('is_active', true)->orderBy('name')->get(['id', 'uuid', 'name', 'color'])];
    }
}
