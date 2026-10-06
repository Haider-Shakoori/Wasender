<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WhatsAppMessageAttempt extends Model
{
    use HasUuid;

    protected $table = 'whatsapp_message_attempts';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['retryable' => 'boolean', 'metadata' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'whatsapp_message_id');
    }
}
