<?php

namespace App\Models;

use App\Enums\WhatsAppCampaignDispatchAttemptStatus;
use App\Enums\WhatsAppCampaignFailureClass;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WhatsAppCampaignDispatchAttempt extends Model
{
    use HasFactory,HasUuid;

    protected $table = 'whatsapp_campaign_dispatch_attempts';

    protected $guarded = ['id', 'tenant_id', 'whatsapp_campaign_id', 'campaign_execution_id', 'campaign_recipient_id', 'recipient_execution_id', 'session_id', 'attempt_number', 'status', 'idempotency_key', 'transport_request_hash', 'transport_reference', 'failure_class', 'failure_code', 'failure_message'];

    protected function casts(): array
    {
        return ['status' => WhatsAppCampaignDispatchAttemptStatus::class, 'failure_class' => WhatsAppCampaignFailureClass::class, 'metadata' => 'array', 'queued_at' => 'datetime', 'started_at' => 'datetime', 'transport_requested_at' => 'datetime', 'transport_accepted_at' => 'datetime', 'succeeded_at' => 'datetime', 'failed_at' => 'datetime', 'next_retry_at' => 'datetime', 'unknown_since' => 'datetime', 'last_reconciled_at' => 'datetime'];
    }

    public function recipientExecution(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaignRecipientExecution::class, 'recipient_execution_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WhatsAppSession::class, 'session_id');
    }
}
