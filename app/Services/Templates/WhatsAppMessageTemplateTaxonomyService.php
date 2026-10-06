<?php

namespace App\Services\Templates;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Templates\AssignWhatsAppMessageTemplateLabelsData;
use App\Data\Templates\TemplateCategoryData;
use App\Data\Templates\TemplateLabelData;
use App\Models\User;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppMessageTemplateCategory;
use App\Models\WhatsAppMessageTemplateLabel;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WhatsAppMessageTemplateTaxonomyService
{
    public function __construct(private TenantContext $context, private TenantEntitlements $entitlements, private AuditService $audit) {}

    public function saveCategory(?WhatsAppMessageTemplateCategory $category, TemplateCategoryData $data, User $actor): WhatsAppMessageTemplateCategory
    {
        $this->entitlements->requireFeature('whatsapp_message_templates');

        return DB::transaction(function () use ($category, $data, $actor): WhatsAppMessageTemplateCategory {
            if ($category && $category->tenant_id !== $this->context->id()) {
                abort(404);
            }
            $category ??= new WhatsAppMessageTemplateCategory;
            $category->forceFill(['tenant_id' => $this->context->id(), 'name' => $data->name, 'slug' => $this->uniqueSlug(WhatsAppMessageTemplateCategory::class, $data->name, $category->id), 'description' => $data->description, 'is_active' => true, $category->exists ? 'updated_by' : 'created_by' => $actor->id])->save();
            $this->audit->recordDomain($category->wasRecentlyCreated ? 'whatsapp_message_template_category.created' : 'whatsapp_message_template_category.updated', $actor, $this->context->get(), $category, ['category_uuid' => $category->uuid]);

            return $category;
        });
    }

    public function saveLabel(?WhatsAppMessageTemplateLabel $label, TemplateLabelData $data, User $actor): WhatsAppMessageTemplateLabel
    {
        $this->entitlements->requireFeature('whatsapp_message_templates');
        if ($label && $label->tenant_id !== $this->context->id()) {
            abort(404);
        }
        if ($data->color && ! in_array($data->color, config('whatsapp_message_templates.label_colors'), true)) {
            throw ValidationException::withMessages(['color' => 'Select an approved label color.']);
        }
        $label ??= new WhatsAppMessageTemplateLabel;
        $label->forceFill(['tenant_id' => $this->context->id(), 'name' => $data->name, 'slug' => $this->uniqueSlug(WhatsAppMessageTemplateLabel::class, $data->name, $label->id), 'color' => $data->color, 'is_active' => true, $label->exists ? 'updated_by' : 'created_by' => $actor->id])->save();
        $this->audit->recordDomain($label->wasRecentlyCreated ? 'whatsapp_message_template_label.created' : 'whatsapp_message_template_label.updated', $actor, $this->context->get(), $label, ['label_uuid' => $label->uuid]);

        return $label;
    }

    public function archive(object $model, User $actor): void
    {
        $this->entitlements->requireFeature('whatsapp_message_templates');
        abort_unless($model->tenant_id === $this->context->id(), 404);
        $model->forceFill(['is_active' => false, 'updated_by' => $actor->id])->save();
        $model->delete();
        $type = $model instanceof WhatsAppMessageTemplateCategory ? 'category' : 'label';
        $this->audit->recordDomain("whatsapp_message_template_{$type}.archived", $actor, $this->context->get(), $model, [$type.'_uuid' => $model->uuid]);
    }

    public function restoreCategory(string $uuid, User $actor): WhatsAppMessageTemplateCategory
    {
        $this->entitlements->requireFeature('whatsapp_message_templates');
        $category = WhatsAppMessageTemplateCategory::withTrashed()->where('tenant_id', $this->context->id())->where('uuid', $uuid)->firstOrFail();
        $category->restore();
        $category->forceFill(['is_active' => true, 'updated_by' => $actor->id])->save();
        $this->audit->recordDomain('whatsapp_message_template_category.restored', $actor, $this->context->get(), $category, ['category_uuid' => $category->uuid]);

        return $category;
    }

    public function restoreLabel(string $uuid, User $actor): WhatsAppMessageTemplateLabel
    {
        $this->entitlements->requireFeature('whatsapp_message_templates');
        $label = WhatsAppMessageTemplateLabel::withTrashed()->where('tenant_id', $this->context->id())->where('uuid', $uuid)->firstOrFail();
        $label->restore();
        $label->forceFill(['is_active' => true, 'updated_by' => $actor->id])->save();
        $this->audit->recordDomain('whatsapp_message_template_label.restored', $actor, $this->context->get(), $label, ['label_uuid' => $label->uuid]);

        return $label;
    }

    public function assignLabels(WhatsAppMessageTemplate $template, AssignWhatsAppMessageTemplateLabelsData $data, User $actor): void
    {
        $this->entitlements->requireFeature('whatsapp_message_templates');
        abort_unless($template->tenant_id === $this->context->id(), 404);
        $labels = WhatsAppMessageTemplateLabel::forTenant($this->context->id())->whereIn('uuid', array_unique($data->labelUuids))->where('is_active', true)->get();
        if ($labels->count() !== count(array_unique($data->labelUuids))) {
            throw ValidationException::withMessages(['label_uuids' => 'One or more labels are invalid for this workspace.']);
        }
        $template->labels()->sync($labels->mapWithKeys(fn ($label) => [$label->id => ['tenant_id' => $this->context->id()]])->all());
        $this->audit->recordDomain('whatsapp_message_template.labels_changed', $actor, $this->context->get(), $template, ['template_uuid' => $template->uuid, 'label_count' => $labels->count()]);
    }

    public function assignCategory(WhatsAppMessageTemplate $template, ?string $uuid, User $actor): void
    {
        $this->entitlements->requireFeature('whatsapp_message_templates');
        abort_unless($template->tenant_id === $this->context->id(), 404);
        $category = $uuid ? WhatsAppMessageTemplateCategory::forTenant($this->context->id())->where('uuid', $uuid)->where('is_active', true)->firstOrFail() : null;
        $template->forceFill(['category_id' => $category?->id, 'updated_by' => $actor->id])->save();
        $this->audit->recordDomain('whatsapp_message_template.category_changed', $actor, $this->context->get(), $template, ['template_uuid' => $template->uuid, 'category_uuid' => $category?->uuid]);
    }

    private function uniqueSlug(string $model, string $name, ?int $except): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $i = 2;
        while ($model::withTrashed()->where('tenant_id', $this->context->id())->where('slug', $slug)->when($except, fn ($q) => $q->whereKeyNot($except))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
