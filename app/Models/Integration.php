<?php

namespace App\Models;

use App\Enums\IntegrationProvider;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Integration extends Model
{
    use HasUuid;

    protected $guarded = ['id'];

    protected $hidden = ['credentials_encrypted'];

    protected function casts(): array
    {
        return ['provider' => IntegrationProvider::class, 'is_enabled' => 'boolean', 'configuration' => 'array', 'credentials_encrypted' => 'encrypted:array', 'last_used_at' => 'datetime', 'last_success_at' => 'datetime', 'last_failure_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(IntegrationEvent::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(IntegrationWebhookDelivery::class);
    }

    public function scopeForTenant(Builder $q, Tenant|int $t): Builder
    {
        return $q->where('tenant_id', $t instanceof Tenant ? $t->id : $t);
    }
}
