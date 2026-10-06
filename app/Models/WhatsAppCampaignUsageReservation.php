<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

final class WhatsAppCampaignUsageReservation extends Model
{
    use HasUuid;

    protected $table = 'whatsapp_campaign_usage_reservations';

    protected $guarded = ['id', 'tenant_id', 'status', 'reserved_units', 'consumed_units', 'released_units'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function available(): int
    {
        return max(0, $this->reserved_units - $this->consumed_units - $this->released_units);
    }
}
