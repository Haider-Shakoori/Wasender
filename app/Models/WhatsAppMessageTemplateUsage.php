<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WhatsAppMessageTemplateUsage extends Model
{
    use HasUuid;

    protected $table = 'whatsapp_message_template_usages';

    protected $guarded = ['id', 'uuid'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageTemplate::class, 'whatsapp_message_template_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageTemplateVersion::class, 'whatsapp_message_template_version_id');
    }
}
