<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

final class WhatsAppMessageTemplateAttachment extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'whatsapp_message_template_attachments';

    protected $guarded = ['id', 'uuid', 'tenant_id', 'whatsapp_message_template_id', 'whatsapp_message_template_version_id', 'disk', 'storage_key', 'mime_type', 'size_bytes', 'checksum_sha256', 'media_category', 'created_by'];

    protected static function booted(): void
    {
        $immutable = function (self $attachment): void {
            if ($attachment->version?->status?->immutable()) {
                throw ValidationException::withMessages(['attachment' => 'Published template attachments are immutable.']);
            }
        };
        self::updating($immutable);
        self::deleting($immutable);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageTemplate::class, 'whatsapp_message_template_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageTemplateVersion::class, 'whatsapp_message_template_version_id');
    }
}
