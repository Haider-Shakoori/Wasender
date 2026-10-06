<?php

namespace App\Models;

use App\Enums\ContactConsentStatus;
use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Contact extends Model
{
    use HasUuid,SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => ContactStatus::class, 'consent_status' => ContactConsentStatus::class, 'source' => ContactSource::class, 'custom_attributes' => 'array', 'consent_recorded_at' => 'datetime', 'consent_expires_at' => 'datetime', 'opted_out_at' => 'datetime', 'suppressed_at' => 'datetime', 'blocked_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ContactConsentEvent::class);
    }

    public function whatsappConversations(): HasMany
    {
        return $this->hasMany(WhatsAppConversation::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class, 'contact_group_members')->withPivot(['tenant_id', 'uuid']);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(ContactLabel::class, 'contact_label_assignments')->withPivot(['tenant_id', 'uuid']);
    }

    public function maskedPhone(): string
    {
        return str_repeat('•', max(0, strlen($this->phone_normalized) - 4)).substr($this->phone_normalized, -4);
    }
}
