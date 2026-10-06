<?php

namespace App\Models;

use App\Enums\WhatsAppCampaignExecutionStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WhatsAppCampaignExecution extends Model
{
    use HasFactory,HasUuid;

    protected $table = 'whatsapp_campaign_executions';

    protected $guarded = ['id', 'tenant_id', 'whatsapp_campaign_id', 'preparation_id', 'usage_reservation_id', 'status', 'campaign_version', 'campaign_payload_hash', 'total_recipients', 'pending_recipients', 'queued_recipients', 'processing_recipients', 'sent_recipients', 'failed_recipients', 'skipped_recipients', 'cancelled_recipients', 'retry_scheduled_recipients', 'transport_pending_recipients', 'progress_percentage'];

    protected function casts(): array
    {
        return ['status' => WhatsAppCampaignExecutionStatus::class, 'metadata' => 'array', 'scheduled_at' => 'datetime', 'queued_at' => 'datetime', 'started_at' => 'datetime', 'pause_requested_at' => 'datetime', 'paused_at' => 'datetime', 'resume_requested_at' => 'datetime', 'resumed_at' => 'datetime', 'cancel_requested_at' => 'datetime', 'cancelled_at' => 'datetime', 'completed_at' => 'datetime', 'failed_at' => 'datetime', 'last_progress_at' => 'datetime', 'last_heartbeat_at' => 'datetime', 'last_connector_event_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class, 'whatsapp_campaign_id');
    }

    public function preparation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaignPreparation::class, 'preparation_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaignUsageReservation::class, 'usage_reservation_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignRecipientExecution::class, 'campaign_execution_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignDispatchAttempt::class, 'campaign_execution_id');
    }
}
