<?php

namespace App\Models;

use App\Enums\AutomationTriggerType;
use App\Enums\AutomationWorkflowVersionStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

final class AutomationWorkflowVersion extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['status' => AutomationWorkflowVersionStatus::class, 'trigger_type' => AutomationTriggerType::class, 'trigger_configuration' => 'array', 'settings' => 'array', 'validation_summary' => 'array', 'validated_at' => 'datetime', 'published_at' => 'datetime', 'superseded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::updating(function (self $v): void {
            if ($v->getRawOriginal('status') !== AutomationWorkflowVersionStatus::Draft->value) {
                throw ValidationException::withMessages(['version' => 'Published workflow versions are immutable.']);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['version' => 'Workflow versions cannot be deleted.']));
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflow::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AutomationWorkflowStep::class)->orderBy('position');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(AutomationWorkflowExecution::class);
    }
}
