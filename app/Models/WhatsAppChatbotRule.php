<?php

namespace App\Models;

use App\Enums\WhatsAppChatbotActionType;
use App\Enums\WhatsAppChatbotMatchType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WhatsAppChatbotRule extends Model
{
    use HasUuid;

    protected $table = 'whatsapp_chatbot_rules';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['match_type' => WhatsAppChatbotMatchType::class, 'action_type' => WhatsAppChatbotActionType::class, 'is_enabled' => 'boolean', 'stop_processing' => 'boolean', 'condition_definition' => 'array', 'action_configuration' => 'array'];
    }

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(WhatsAppChatbot::class);
    }
}
