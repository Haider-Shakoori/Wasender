<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Tenant extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'name', 'slug', 'email', 'phone', 'country', 'timezone',
        'currency', 'locale', 'logo_path', 'status', 'is_active', 'owner_id',
        'suspended_at', 'suspended_by', 'suspension_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public function platformNotes(): MorphMany
    {
        return $this->morphMany(PlatformNote::class, 'subject');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(TenantMembership::class)
            ->withPivot(['id', 'role_id', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function billingPayments(): HasMany
    {
        return $this->hasMany(BillingPayment::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class)->where('is_current', true)->latestOfMany();
    }

    public function whatsappSessions(): HasMany
    {
        return $this->hasMany(WhatsAppSession::class);
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    public function whatsappConversations(): HasMany
    {
        return $this->hasMany(WhatsAppConversation::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TenantStatus::Active)->where('is_active', true);
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('status', TenantStatus::Suspended);
    }

    public function scopeOwnedBy(Builder $query, User|int $owner): Builder
    {
        return $query->where('owner_id', $owner instanceof User ? $owner->getKey() : $owner);
    }
}
