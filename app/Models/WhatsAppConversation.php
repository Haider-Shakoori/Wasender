<?php

namespace App\Models;

use App\Enums\WhatsAppConversationPriority;
use App\Enums\WhatsAppConversationStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WhatsAppConversation extends Model
{
    use HasUuid;

    protected $table = 'whatsapp_conversations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => WhatsAppConversationStatus::class, 'priority' => WhatsAppConversationPriority::class, 'metadata' => 'array', 'last_message_at' => 'datetime', 'last_inbound_at' => 'datetime', 'last_outbound_at' => 'datetime', 'first_message_at' => 'datetime', 'last_read_at' => 'datetime', 'last_agent_activity_at' => 'datetime', 'closed_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WhatsAppSession::class, 'whatsapp_session_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppInboxMessage::class);
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInboxMessage::class, 'last_message_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(WhatsAppConversationNote::class, 'whatsapp_conversation_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(WhatsAppConversationActivity::class, 'whatsapp_conversation_id');
    }

    public function chatbotStates(): HasMany
    {
        return $this->hasMany(WhatsAppChatbotConversationState::class, 'whatsapp_conversation_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(WhatsAppConversationLabel::class, 'whatsapp_conversation_label_assignments', 'whatsapp_conversation_id', 'whatsapp_conversation_label_id')->withPivot(['tenant_id', 'uuid', 'assigned_by']);
    }

    public function scopeForTenant(Builder $query, Tenant|int $tenant): Builder
    {
        return $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->id : $tenant);
    }
}
