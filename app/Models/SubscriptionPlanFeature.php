<?php

namespace App\Models;

use App\Enums\PlanFeatureValueType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubscriptionPlanFeature extends Model
{
    use HasFactory;

    protected $fillable = ['plan_id', 'feature_key', 'value_type', 'boolean_value', 'integer_value', 'string_value', 'is_unlimited'];

    protected function casts(): array
    {
        return ['value_type' => PlanFeatureValueType::class, 'boolean_value' => 'boolean', 'integer_value' => 'integer', 'is_unlimited' => 'boolean'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
