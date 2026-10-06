<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Data\Templates\AssignWhatsAppMessageTemplateLabelsData;
use App\Data\Templates\TemplateCategoryData;
use App\Data\Templates\TemplateLabelData;
use App\Http\Requests\AssignWhatsAppMessageTemplateLabelsRequest;
use App\Http\Requests\StoreWhatsAppMessageTemplateCategoryRequest;
use App\Http\Requests\StoreWhatsAppMessageTemplateLabelRequest;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppMessageTemplateCategory;
use App\Models\WhatsAppMessageTemplateLabel;
use App\Services\Templates\WhatsAppMessageTemplateTaxonomyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WhatsAppMessageTemplateTaxonomyController extends Controller
{
    public function categories(Request $request, TenantContext $context): JsonResponse
    {
        $this->authorize('viewAny', WhatsAppMessageTemplateCategory::class);

        return response()->json(WhatsAppMessageTemplateCategory::forTenant($context->id())->orderBy('name')->paginate(25));
    }

    public function storeCategory(StoreWhatsAppMessageTemplateCategoryRequest $request, WhatsAppMessageTemplateTaxonomyService $service): JsonResponse
    {
        $this->authorize('create', WhatsAppMessageTemplateCategory::class);

        return response()->json($service->saveCategory(null, TemplateCategoryData::from($request->validated()), $request->user()), 201);
    }

    public function updateCategory(StoreWhatsAppMessageTemplateCategoryRequest $request, WhatsAppMessageTemplateCategory $category, WhatsAppMessageTemplateTaxonomyService $service): JsonResponse
    {
        $this->authorize('update', $category);

        return response()->json($service->saveCategory($category, TemplateCategoryData::from($request->validated()), $request->user()));
    }

    public function archiveCategory(Request $request, WhatsAppMessageTemplateCategory $category, WhatsAppMessageTemplateTaxonomyService $service): JsonResponse
    {
        $this->authorize('update', $category);
        $service->archive($category, $request->user());

        return response()->json(null, 204);
    }

    public function restoreCategory(Request $request, string $categoryUuid, WhatsAppMessageTemplateTaxonomyService $service): JsonResponse
    {
        $this->authorize('create', WhatsAppMessageTemplateCategory::class);

        return response()->json($service->restoreCategory($categoryUuid, $request->user()));
    }

    public function labels(Request $request, TenantContext $context): JsonResponse
    {
        $this->authorize('viewAny', WhatsAppMessageTemplateLabel::class);

        return response()->json(WhatsAppMessageTemplateLabel::forTenant($context->id())->orderBy('name')->paginate(25));
    }

    public function storeLabel(StoreWhatsAppMessageTemplateLabelRequest $request, WhatsAppMessageTemplateTaxonomyService $service): JsonResponse
    {
        $this->authorize('create', WhatsAppMessageTemplateLabel::class);

        return response()->json($service->saveLabel(null, TemplateLabelData::from($request->validated()), $request->user()), 201);
    }

    public function updateLabel(StoreWhatsAppMessageTemplateLabelRequest $request, WhatsAppMessageTemplateLabel $label, WhatsAppMessageTemplateTaxonomyService $service): JsonResponse
    {
        $this->authorize('update', $label);

        return response()->json($service->saveLabel($label, TemplateLabelData::from($request->validated()), $request->user()));
    }

    public function archiveLabel(Request $request, WhatsAppMessageTemplateLabel $label, WhatsAppMessageTemplateTaxonomyService $service): JsonResponse
    {
        $this->authorize('update', $label);
        $service->archive($label, $request->user());

        return response()->json(null, 204);
    }

    public function restoreLabel(Request $request, string $labelUuid, WhatsAppMessageTemplateTaxonomyService $service): JsonResponse
    {
        $this->authorize('create', WhatsAppMessageTemplateLabel::class);

        return response()->json($service->restoreLabel($labelUuid, $request->user()));
    }

    public function assign(AssignWhatsAppMessageTemplateLabelsRequest $request, WhatsAppMessageTemplate $template, TenantContext $context, WhatsAppMessageTemplateTaxonomyService $service): JsonResponse
    {
        abort_unless($template->tenant_id === $context->id(), 404);
        if ($request->has('label_uuids')) {
            $this->authorize('manageLabels', $template);
            $service->assignLabels($template, AssignWhatsAppMessageTemplateLabelsData::from($request->validated()), $request->user());
        }
        if ($request->has('category_uuid')) {
            $this->authorize('manageCategory', $template);
            $service->assignCategory($template, $request->validated('category_uuid'), $request->user());
        }

        return $request->expectsJson() ? response()->json(['updated' => true]) : back()->with('status', 'Template organization updated.');
    }
}
