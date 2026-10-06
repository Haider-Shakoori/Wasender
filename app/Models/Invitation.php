<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantInvitationStatus;
use App\Models\Concerns\HasUuid;
use Database\Factories\TenantInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Invitation extends Model
{
    /** @use HasFactory<TenantInvitationFactory> */
    use HasFactory, HasUuid;

    protected $table = 'tenant_invitations';

    protected $fillable = [
        'uuid', 'tenant_id', 'email', 'role_id', 'token_hash', 'status', 'expires_at',
        'invited_by', 'accepted_at', 'last_sent_at', 'send_count', 'revoked_at', 'revoked_by',
    ];

    protected $hidden = ['token_hash'];

    protected static function newFactory(): TenantInvitationFactory
    {
        return TenantInvitationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => TenantInvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'revoked_at' => 'datetime',
            'send_count' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
