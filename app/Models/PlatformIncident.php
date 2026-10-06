<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

final class PlatformIncident extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'dedupe_key',
        'severity',
        'status',
        'title',
        'message',
        'context',
        'occurrences',
        'first_seen_at',
        'last_seen_at',
        'last_notified_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurrences' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
