<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WhatsAppChatbotRuleExecution extends Model
{
    use HasUuid;

    protected $table = 'whatsapp_chatbot_rule_executions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(WhatsAppChatbot::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(WhatsAppChatbotRule::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }

    public function inboundMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInboxMessage::class, 'inbound_message_id');
    }

    public function outboundMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'outbound_message_id');
    }
}
