<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class WhatsAppConversationLabel extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'whatsapp_conversation_labels';

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeForTenant(Builder $q, int $id): Builder
    {
        return $q->where('tenant_id', $id);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(WhatsAppConversation::class, 'whatsapp_conversation_label_assignments', 'whatsapp_conversation_label_id', 'whatsapp_conversation_id')->withPivot(['tenant_id', 'uuid', 'assigned_by']);
    }
}
