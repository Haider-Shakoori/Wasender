<?php

namespace App\Models;

use App\Enums\WhatsAppCampaignAudienceType;
use App\Enums\WhatsAppCampaignScheduleType;
use App\Enums\WhatsAppCampaignSessionStrategy;
use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class WhatsAppCampaign extends Model
{
    use HasUuid,SoftDeletes;

    protected $table = 'whatsapp_campaigns';

    protected $guarded = ['id', 'tenant_id', 'status', 'payload_hash', 'version', 'snapshot_recipient_count', 'eligible_recipient_count', 'excluded_recipient_count', 'queued_recipient_count', 'processing_recipient_count', 'sent_recipient_count', 'delivered_recipient_count', 'read_recipient_count', 'failed_recipient_count', 'cancelled_recipient_count', 'skipped_recipient_count', 'progress_percentage', 'created_by', 'updated_by', 'approved_by', 'launched_by', 'paused_by', 'cancelled_by', 'completed_by', 'prepared_at', 'started_at', 'completed_at', 'failure_code', 'failure_message'];

    protected function casts(): array
    {
        return ['status' => WhatsAppCampaignStatus::class, 'message_type' => WhatsAppMessageType::class, 'audience_type' => WhatsAppCampaignAudienceType::class, 'session_strategy' => WhatsAppCampaignSessionStrategy::class, 'schedule_type' => WhatsAppCampaignScheduleType::class, 'content_metadata' => 'array', 'template_variable_values' => 'array', 'template_content_customized' => 'boolean', 'audience_config' => 'array', 'session_config' => 'array', 'send_window_config' => 'array', 'execution_config' => 'array', 'metadata' => 'array', 'scheduled_at_utc' => 'datetime', 'template_rendered_at' => 'datetime', 'prepared_at' => 'datetime', 'last_progress_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeForTenant(Builder $q, int|Tenant $t): Builder
    {
        return $q->where('tenant_id', $t instanceof Tenant ? $t->id : $t);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachment(): HasOne
    {
        return $this->hasOne(WhatsAppCampaignAttachment::class);
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
        return $this->hasMany(WhatsAppCampaignEventRecord::class)->orderByDesc('occurred_at');
    }

    public function sessionSelections(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignSession::class);
    }

    public function audienceReferences(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignAudienceReference::class);
    }

    public function preparations(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignPreparation::class);
    }

    public function activePreparation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaignPreparation::class, 'active_preparation_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignRecipient::class);
    }

    public function exclusions(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignExclusion::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignExecution::class);
    }

    public function activeExecution(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaignExecution::class, 'active_execution_id');
    }
}
