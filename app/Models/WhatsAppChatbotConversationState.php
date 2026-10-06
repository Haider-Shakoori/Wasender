<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WhatsAppChatbotConversationState extends Model
{
    use HasUuid;

    protected $table = 'whatsapp_chatbot_conversation_states';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['handoff_active' => 'boolean', 'last_bot_reply_at' => 'datetime', 'cooldown_until' => 'datetime', 'metadata' => 'array'];
    }

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(WhatsAppChatbot::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }

    public function lastRule(): BelongsTo
    {
        return $this->belongsTo(WhatsAppChatbotRule::class, 'last_rule_id');
    }
}
