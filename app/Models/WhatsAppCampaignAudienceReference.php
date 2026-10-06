<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

final class WhatsAppCampaignAudienceReference extends Model
{
    use HasUuid;

    protected $table = 'whatsapp_campaign_audience_refs';

    protected $guarded = ['id'];
}
