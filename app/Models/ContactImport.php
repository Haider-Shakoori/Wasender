<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContactImport extends Model
{
    use HasUuid;

    protected $table = 'contact_imports';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['mapping' => 'array', 'preview' => 'array', 'completed_at' => 'datetime', 'rolled_back_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
