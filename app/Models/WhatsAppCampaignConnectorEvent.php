<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

final class WhatsAppCampaignConnectorEvent extends Model
{
    use HasUuid;

    protected $table = 'whatsapp_campaign_connector_events';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
