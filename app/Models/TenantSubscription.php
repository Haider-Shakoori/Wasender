<?php

namespace App\Models;

use App\Enums\SubscriptionSource;
use App\Enums\TenantSubscriptionStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TenantSubscription extends Model
{
    use HasFactory,HasUuid;

    protected $fillable = ['uuid', 'tenant_id', 'plan_id', 'status', 'source', 'is_current', 'starts_at', 'trial_ends_at', 'current_period_starts_at', 'current_period_ends_at', 'grace_ends_at', 'cancelled_at', 'ended_at', 'assigned_by', 'provider', 'provider_subscription_id', 'metadata'];

    protected function casts(): array
    {
        return ['status' => TenantSubscriptionStatus::class, 'source' => SubscriptionSource::class, 'is_current' => 'boolean', 'starts_at' => 'datetime', 'trial_ends_at' => 'datetime', 'current_period_starts_at' => 'datetime', 'current_period_ends_at' => 'datetime', 'grace_ends_at' => 'datetime', 'cancelled_at' => 'datetime', 'ended_at' => 'datetime', 'metadata' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(SubscriptionStatusHistory::class, 'subscription_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class, 'subscription_id');
    }

    public function scopeCurrent(Builder $q): Builder
    {
        return $q->where('is_current', true);
    }
}
