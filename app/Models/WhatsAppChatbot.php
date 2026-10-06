<?php

namespace App\Models;

use App\Enums\WhatsAppChatbotStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WhatsAppChatbot extends Model
{
    use HasUuid;

    protected $table = 'whatsapp_chatbots';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => WhatsAppChatbotStatus::class, 'is_enabled' => 'boolean', 'fallback_configuration' => 'array', 'handoff_on_failure' => 'boolean', 'published_at' => 'datetime', 'archived_at' => 'datetime'];
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

    public function sessions(): BelongsToMany
    {
        return $this->belongsToMany(WhatsAppSession::class, 'whatsapp_chatbot_session_assignments', 'chatbot_id', 'whatsapp_session_id')->withPivot(['tenant_id', 'uuid'])->withTimestamps();
    }

    public function rules(): HasMany
    {
        return $this->hasMany(WhatsAppChatbotRule::class, 'chatbot_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(WhatsAppChatbotRuleExecution::class, 'chatbot_id');
    }

    public function scopeForTenant(Builder $q, Tenant|int $t): Builder
    {
        return $q->where('tenant_id', $t instanceof Tenant ? $t->id : $t);
    }
}
