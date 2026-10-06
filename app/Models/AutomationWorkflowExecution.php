<?php

namespace App\Models;

use App\Enums\AutomationWorkflowExecutionStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AutomationWorkflowExecution extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['status' => AutomationWorkflowExecutionStatus::class, 'context' => 'array', 'metadata' => 'array', 'started_at' => 'datetime', 'waiting_until' => 'datetime', 'cancel_requested_at' => 'datetime', 'cancelled_at' => 'datetime', 'completed_at' => 'datetime', 'failed_at' => 'datetime', 'timed_out_at' => 'datetime', 'last_heartbeat_at' => 'datetime'];
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

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflow::class, 'automation_workflow_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflowVersion::class, 'automation_workflow_version_id');
    }

    public function stepExecutions(): HasMany
    {
        return $this->hasMany(AutomationWorkflowStepExecution::class);
    }
}
