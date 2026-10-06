<?php

namespace App\Models;

use App\Enums\TemplateVariableContext;
use App\Enums\WhatsAppMessageTemplateVersionStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

final class WhatsAppMessageTemplateVersion extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'whatsapp_message_template_versions';

    protected $guarded = ['id', 'uuid', 'whatsapp_message_template_id', 'version_number', 'status', 'created_by', 'published_by', 'published_at', 'superseded_at'];

    protected function casts(): array
    {
        return ['status' => WhatsAppMessageTemplateVersionStatus::class, 'content_configuration' => 'array', 'variable_context' => TemplateVariableContext::class, 'variable_configuration' => 'array', 'published_at' => 'datetime', 'superseded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::updating(function (self $version): void {
            if ($version->getRawOriginal('status') !== WhatsAppMessageTemplateVersionStatus::Draft->value) {
                throw ValidationException::withMessages(['version' => 'Published template versions are immutable.']);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['version' => 'Template versions cannot be deleted.']));
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageTemplate::class, 'whatsapp_message_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function attachment(): HasOne
    {
        return $this->hasOne(WhatsAppMessageTemplateAttachment::class, 'whatsapp_message_template_version_id')->whereNull('deleted_at');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(WhatsAppMessageTemplateUsage::class, 'whatsapp_message_template_version_id');
    }
}
