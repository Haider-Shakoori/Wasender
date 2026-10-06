<?php

namespace App\Models;

use App\Enums\AutomationWorkflowFailureClass;
use App\Enums\AutomationWorkflowStepExecutionStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AutomationWorkflowStepExecution extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['status' => AutomationWorkflowStepExecutionStatus::class, 'failure_class' => AutomationWorkflowFailureClass::class, 'input_snapshot' => 'array', 'output_snapshot' => 'array', 'started_at' => 'datetime', 'waiting_until' => 'datetime', 'completed_at' => 'datetime', 'failed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflowExecution::class, 'automation_workflow_execution_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflowStep::class, 'automation_workflow_step_id');
    }
}
