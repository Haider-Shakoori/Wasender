<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WhatsAppCampaignSession extends Model
{
    use HasUuid;

    protected $table = 'whatsapp_campaign_sessions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WhatsAppSession::class, 'whatsapp_session_id');
    }
}
