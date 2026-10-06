<?php

namespace App\Models;

use App\Enums\WhatsAppCampaignExclusionReason;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WhatsAppCampaignExclusion extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'whatsapp_campaign_exclusions';

    public $timestamps = false;

    protected $guarded = ['id', 'tenant_id', 'whatsapp_campaign_id', 'preparation_id', 'contact_id', 'contact_uuid', 'deduplication_key', 'reason_code', 'reason_codes', 'source_type', 'source_reference', 'safe_summary'];

    protected function casts(): array
    {
        return ['reason_code' => WhatsAppCampaignExclusionReason::class, 'reason_codes' => 'array', 'created_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function preparation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaignPreparation::class, 'preparation_id');
    }
}
