<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WhatsAppSessionEvent extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'whatsapp_session_events';

    public $timestamps = false;

    protected $fillable = [
        'uuid', 'tenant_id', 'whatsapp_session_id', 'event', 'from_status',
        'to_status', 'source', 'reason_code', 'message', 'metadata',
        'occurred_at', 'created_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WhatsAppSession::class, 'whatsapp_session_id');
    }
}
