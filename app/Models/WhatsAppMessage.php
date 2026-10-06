<?php

namespace App\Models;

use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class WhatsAppMessage extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'whatsapp_messages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['message_type' => WhatsAppMessageType::class, 'status' => WhatsAppMessageStatus::class, 'failure_retryable' => 'boolean', 'metadata' => 'array', 'template_variable_values' => 'array', 'template_rendered_at' => 'datetime',
            'queued_at' => 'datetime', 'processing_at' => 'datetime', 'sending_at' => 'datetime', 'sent_at' => 'datetime', 'delivered_at' => 'datetime',
            'read_at' => 'datetime', 'failed_at' => 'datetime', 'cancelled_at' => 'datetime', 'expired_at' => 'datetime', 'last_attempt_at' => 'datetime',
            'next_retry_at' => 'datetime', 'expires_at' => 'datetime'];
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachment(): HasOne
    {
        return $this->hasOne(WhatsAppMessageAttachment::class, 'whatsapp_message_id');
    }

    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageTemplate::class, 'message_template_id');
    }

    public function messageTemplateVersion(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageTemplateVersion::class, 'message_template_version_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(WhatsAppMessageEvent::class, 'whatsapp_message_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(WhatsAppMessageAttempt::class, 'whatsapp_message_id');
    }

    public function inboxProjection(): HasOne
    {
        return $this->hasOne(WhatsAppInboxMessage::class, 'outbound_message_id');
    }

    public function maskedRecipient(): string
    {
        return str_repeat('•', max(0, strlen($this->recipient_normalized) - 4)).substr($this->recipient_normalized, -4);
    }
}
