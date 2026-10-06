<?php

namespace App\Models;

use App\Enums\WhatsAppCampaignAudienceType;
use App\Enums\WhatsAppCampaignPreparationStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WhatsAppCampaignPreparation extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'whatsapp_campaign_preparations';

    protected $guarded = ['id', 'tenant_id', 'whatsapp_campaign_id', 'status', 'campaign_version', 'campaign_payload_hash', 'audience_definition_hash', 'total_candidates', 'processed_candidates', 'eligible_count', 'excluded_count', 'duplicate_count', 'invalid_count', 'progress_percentage', 'cursor_state', 'started_at', 'completed_at', 'failed_at', 'cancelled_at', 'failure_code', 'failure_message'];

    protected function casts(): array
    {
        return ['status' => WhatsAppCampaignPreparationStatus::class, 'audience_type' => WhatsAppCampaignAudienceType::class, 'cursor_state' => 'array', 'metadata' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'failed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class, 'whatsapp_campaign_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignRecipient::class, 'preparation_id');
    }

    public function exclusions(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignExclusion::class, 'preparation_id');
    }
}
