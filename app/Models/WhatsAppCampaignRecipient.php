<?php

namespace App\Models;

use App\Enums\ContactConsentStatus;
use App\Enums\WhatsAppCampaignRecipientStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class WhatsAppCampaignRecipient extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'whatsapp_campaign_recipients';

    protected $guarded = ['id', 'tenant_id', 'whatsapp_campaign_id', 'preparation_id', 'contact_id', 'contact_uuid', 'phone_normalized', 'whatsapp_address', 'display_name', 'preferred_language', 'timezone', 'consent_status', 'recipient_status', 'source_type', 'source_reference', 'deduplication_key', 'snapshot_data', 'campaign_version', 'campaign_payload_hash', 'prepared_at'];

    protected function casts(): array
    {
        return ['consent_status' => ContactConsentStatus::class, 'recipient_status' => WhatsAppCampaignRecipientStatus::class, 'snapshot_data' => 'array', 'prepared_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class, 'whatsapp_campaign_id');
    }

    public function preparation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaignPreparation::class, 'preparation_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function execution(): HasOne
    {
        return $this->hasOne(WhatsAppCampaignRecipientExecution::class, 'campaign_recipient_id')->latestOfMany();
    }

    public function maskedPhone(): string
    {
        return str_repeat('•', max(0, strlen($this->phone_normalized) - 4)).substr($this->phone_normalized, -4);
    }
}
