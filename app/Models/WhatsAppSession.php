<?php

namespace App\Models;

use App\Enums\WhatsAppSessionStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class WhatsAppSession extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'uuid', 'tenant_id', 'name', 'storage_key', 'status', 'phone_number',
        'display_name', 'platform', 'wid', 'last_qr_generated_at',
        'qr_generation_count', 'authenticated_at', 'ready_at', 'last_seen_at',
        'last_health_check_at', 'disconnected_at', 'disconnect_reason',
        'failure_code', 'failure_message', 'reconnect_attempts',
        'last_reconnect_attempt_at', 'created_by', 'updated_by',
    ];

    protected $hidden = ['storage_key'];

    protected function casts(): array
    {
        return [
            'status' => WhatsAppSessionStatus::class,
            'last_qr_generated_at' => 'datetime',
            'authenticated_at' => 'datetime',
            'ready_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_health_check_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'last_reconnect_attempt_at' => 'datetime',
            'deleted_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(WhatsAppSessionEvent::class, 'whatsapp_session_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_session_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsAppConversation::class, 'whatsapp_session_id');
    }

    public function campaignRecipientExecutions(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignRecipientExecution::class, 'session_id');
    }

    public function scopeForTenant(Builder $query, Tenant|int $tenant): Builder
    {
        return $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->id : $tenant);
    }

    public function scopeCapacityConsuming(Builder $query): Builder
    {
        return $query->where('status', '!=', WhatsAppSessionStatus::Deleted);
    }
}
