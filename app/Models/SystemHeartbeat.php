<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SystemHeartbeat extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'ran_at', 'metadata'];

    protected function casts(): array
    {
        return ['ran_at' => 'datetime', 'metadata' => 'array'];
    }
}
