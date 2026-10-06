<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionPlanStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SubscriptionPlan extends Model
{
    use HasFactory,HasUuid;

    protected $fillable = ['uuid', 'name', 'slug', 'description', 'status', 'is_public', 'is_featured', 'sort_order', 'billing_interval', 'price_amount', 'price_currency', 'trial_days', 'grace_days', 'is_system'];

    protected function casts(): array
    {
        return ['status' => SubscriptionPlanStatus::class, 'billing_interval' => BillingInterval::class, 'is_public' => 'boolean', 'is_featured' => 'boolean', 'is_system' => 'boolean', 'price_amount' => 'integer', 'trial_days' => 'integer', 'grace_days' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function features(): HasMany
    {
        return $this->hasMany(SubscriptionPlanFeature::class, 'plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }
}
