<?php

namespace App\Models;

use App\Enums\WhatsAppCampaignRecipientExecutionStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WhatsAppCampaignRecipientExecution extends Model
{
    use HasFactory,HasUuid;

    protected $table = 'whatsapp_campaign_recipient_executions';

    protected $guarded = ['id', 'tenant_id', 'whatsapp_campaign_id', 'campaign_execution_id', 'campaign_recipient_id', 'status', 'session_id', 'attempt_count', 'max_attempts', 'next_attempt_at', 'claimed_at', 'processing_started_at', 'last_attempt_at', 'sent_at', 'failed_at', 'skipped_at', 'cancelled_at', 'failure_code', 'failure_message', 'transport_reference', 'lock_version'];

    protected function casts(): array
    {
        return ['status' => WhatsAppCampaignRecipientExecutionStatus::class, 'metadata' => 'array', 'next_attempt_at' => 'datetime', 'claimed_at' => 'datetime', 'processing_started_at' => 'datetime', 'last_attempt_at' => 'datetime', 'sent_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime', 'usage_consumed_at' => 'datetime', 'last_connector_event_at' => 'datetime', 'failed_at' => 'datetime', 'skipped_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaignExecution::class, 'campaign_execution_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaignRecipient::class, 'campaign_recipient_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WhatsAppSession::class, 'session_id');
    }
}
