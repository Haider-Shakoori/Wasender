<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class WhatsAppMessageTemplateCategory extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'whatsapp_message_template_categories';

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

    public function templates(): HasMany
    {
        return $this->hasMany(WhatsAppMessageTemplate::class, 'category_id');
    }
}
