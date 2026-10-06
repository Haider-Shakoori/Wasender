<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class WhatsAppMessageTemplateLabel extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'whatsapp_message_template_labels';

    protected $guarded = ['id', 'uuid', 'tenant_id', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeForTenant(Builder $query, int $tenant): Builder
    {
        return $query->where('tenant_id', $tenant);
    }

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(WhatsAppMessageTemplate::class, 'whatsapp_message_template_label_assignments', 'whatsapp_message_template_label_id', 'whatsapp_message_template_id')->withPivot('tenant_id');
    }
}
