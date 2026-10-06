<?php

namespace App\Models;

use App\Enums\WhatsAppInboxMediaStatus;
use App\Enums\WhatsAppInboxMessageDirection;
use App\Enums\WhatsAppInboxMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WhatsAppInboxMessage extends Model
{
    use HasUuid;

    protected $table = 'whatsapp_inbox_messages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['direction' => WhatsAppInboxMessageDirection::class, 'message_type' => WhatsAppMessageType::class, 'status' => WhatsAppInboxMessageStatus::class, 'media_status' => WhatsAppInboxMediaStatus::class, 'metadata' => 'array', 'occurred_at' => 'datetime', 'received_at' => 'datetime', 'sent_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WhatsAppSession::class, 'whatsapp_session_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function outboundMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'outbound_message_id');
    }

    public function replyToMessage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }
}
