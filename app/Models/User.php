<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuid, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'last_active_tenant_id',
        'is_platform_admin',
        'uuid', 'status', 'suspended_at', 'suspended_by', 'suspension_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'status' => UserStatus::class,
            'suspended_at' => 'datetime',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->using(TenantMembership::class)
            ->withPivot(['id', 'role_id', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function ownedTenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'owner_id');
    }

    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function lastActiveTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'last_active_tenant_id');
    }

    public function belongsToTenant(Tenant|int $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        return $this->tenantMemberships()->where('tenant_id', $tenantId)->exists();
    }

    public function hasActiveMembership(Tenant|int $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        return $this->tenantMemberships()->active()->where('tenant_id', $tenantId)->exists();
    }

    public function isPlatformAdmin(): bool
    {
        // Compatibility accessor only. Authorization never consults this legacy flag.
        return $this->is_platform_admin || $this->platformRoles()->exists();
    }

    public function platformRoles(): BelongsToMany
    {
        return $this->belongsToMany(PlatformRole::class, 'platform_role_user')->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status !== UserStatus::Suspended;
    }

    public function platformNotes(): MorphMany
    {
        return $this->morphMany(PlatformNote::class, 'subject');
    }

    public function hasTenantPermission(Tenant $tenant, string $permission): bool
    {
        return $this->tenantMemberships()
            ->active()
            ->where('tenant_id', $tenant->id)
            ->whereHas('role', fn ($query) => $query
                ->where('tenant_id', $tenant->id)
                ->whereHas('permissions', fn ($permissions) => $permissions->where('slug', $permission)))
            ->exists();
    }
}
