<?php

namespace App\Models;

use App\Enums\AutomationStepType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

final class AutomationWorkflowStep extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['step_type' => AutomationStepType::class, 'configuration' => 'array'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $s): void {
            if ($s->exists && $s->version()->where('status', '!=', 'draft')->exists()) {
                throw ValidationException::withMessages(['step' => 'Published workflow steps are immutable.']);
            }
        });
        self::deleting(function (self $s): void {
            if ($s->version()->where('status', '!=', 'draft')->exists()) {
                throw ValidationException::withMessages(['step' => 'Published workflow steps are immutable.']);
            }
        });
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflowVersion::class, 'automation_workflow_version_id');
    }
}
