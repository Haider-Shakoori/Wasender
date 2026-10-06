<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

final class Role extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = ['uuid', 'tenant_id', 'name', 'slug', 'description', 'is_system'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    protected static function booted(): void
    {
        self::saving(function (Role $role): void {
            if ($role->tenant_id === null && static::query()
                ->whereNull('tenant_id')->where('slug', $role->slug)
                ->when($role->exists, fn (Builder $query) => $query->whereKeyNot($role->getKey()))
                ->exists()) {
                throw ValidationException::withMessages(['slug' => 'A global role template with this slug already exists.']);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')->withPivot(['id', 'tenant_id', 'status', 'joined_at'])->withTimestamps();
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->whereNull('tenant_id')->where('is_system', true);
    }

    public function scopeForTenant(Builder $query, Tenant|int $tenant): Builder
    {
        return $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    public function hasPermission(string $permissionSlug): bool
    {
        return $this->permissions()->where('slug', $permissionSlug)->exists();
    }
}
