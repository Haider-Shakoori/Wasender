<?php

namespace App\Models;

use App\Enums\TenantSubscriptionStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubscriptionStatusHistory extends Model
{
    use HasFactory,HasUuid;

    protected $table = 'subscription_status_history';

    public $timestamps = false;

    protected $fillable = ['uuid', 'subscription_id', 'from_status', 'to_status', 'reason', 'actor_type', 'actor_id', 'effective_at', 'created_at'];

    protected function casts(): array
    {
        return ['from_status' => TenantSubscriptionStatus::class, 'to_status' => TenantSubscriptionStatus::class, 'effective_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class);
    }
}
