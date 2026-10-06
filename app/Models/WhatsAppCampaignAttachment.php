<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class WhatsAppCampaignAttachment extends Model
{
    use HasUuid,SoftDeletes;

    protected $table = 'whatsapp_campaign_attachments';

    protected $guarded = ['id'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class, 'whatsapp_campaign_id');
    }
}
