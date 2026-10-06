<?php

namespace App\Models;

use App\Enums\AutomationWorkflowStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AutomationWorkflow extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['status' => AutomationWorkflowStatus::class, 'is_enabled' => 'boolean', 'published_at' => 'datetime', 'enabled_at' => 'datetime', 'disabled_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeForTenant(Builder $q, int|Tenant $t): Builder
    {
        return $q->where('tenant_id', $t instanceof Tenant ? $t->id : $t);
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
        return $this->hasMany(AutomationWorkflowVersion::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(AutomationWorkflowExecution::class);
    }

    public function latestExecution(): HasOne
    {
        return $this->hasOne(AutomationWorkflowExecution::class)->latestOfMany();
    }

    public function currentDraftVersion(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflowVersion::class, 'current_draft_version_id');
    }

    public function currentPublishedVersion(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflowVersion::class, 'current_published_version_id');
    }
}
