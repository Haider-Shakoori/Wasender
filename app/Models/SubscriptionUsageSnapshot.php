<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubscriptionUsageSnapshot extends Model
{
    use HasFactory,HasUuid;

    protected $fillable = ['uuid', 'tenant_id', 'subscription_id', 'metric_key', 'value', 'period_starts_at', 'captured_at'];

    protected function casts(): array
    {
        return ['value' => 'integer', 'period_starts_at' => 'datetime', 'captured_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class);
    }
}
