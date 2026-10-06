<?php

namespace App\Models;

use App\Enums\WhatsAppMessageTemplateStatus;
use App\Enums\WhatsAppMessageTemplateType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WhatsAppMessageTemplate extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'whatsapp_message_templates';

    protected $guarded = ['id', 'uuid', 'tenant_id', 'status', 'current_draft_version_id', 'current_published_version_id', 'lock_version', 'created_by', 'updated_by', 'published_at', 'archived_at'];

    protected function casts(): array
    {
        return ['type' => WhatsAppMessageTemplateType::class, 'status' => WhatsAppMessageTemplateStatus::class, 'published_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeForTenant(Builder $query, int|Tenant $tenant): Builder
    {
        return $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->id : $tenant);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WhatsAppMessageTemplateVersion::class, 'whatsapp_message_template_id');
    }

    public function currentDraftVersion(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageTemplateVersion::class, 'current_draft_version_id');
    }

    public function currentPublishedVersion(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageTemplateVersion::class, 'current_published_version_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageTemplateCategory::class, 'category_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(WhatsAppMessageTemplateLabel::class, 'whatsapp_message_template_label_assignments', 'whatsapp_message_template_id', 'whatsapp_message_template_label_id')->withPivot('tenant_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(WhatsAppMessageTemplateUsage::class, 'whatsapp_message_template_id');
    }
}
