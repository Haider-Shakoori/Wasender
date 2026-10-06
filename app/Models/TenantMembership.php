<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Models\Concerns\HasUuid;
use App\Services\TenantPermissionCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

final class TenantMembership extends Pivot
{
    use HasFactory, HasUuid;

    protected $table = 'tenant_user';

    public $incrementing = true;

    protected $fillable = ['uuid', 'tenant_id', 'user_id', 'role_id', 'status', 'joined_at'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        self::updated(function (TenantMembership $membership): void {
            if ($membership->wasChanged('role_id')) {
                Role::with('tenant')->whereIn('id', [
                    $membership->getOriginal('role_id'),
                    $membership->role_id,
                ])->get()->each(fn (Role $role) => app(TenantPermissionCache::class)->forget($role));
            }
        });
    }

    protected function casts(): array
    {
        return ['status' => MembershipStatus::class, 'joined_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', MembershipStatus::Active);
    }

    public function scopeInvited(Builder $query): Builder
    {
        return $query->where('status', MembershipStatus::Invited);
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('status', MembershipStatus::Suspended);
    }

    public function scopeForTenant(Builder $query, Tenant|int $tenant): Builder
    {
        return $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        return $query->where('user_id', $user instanceof User ? $user->getKey() : $user);
    }
}
