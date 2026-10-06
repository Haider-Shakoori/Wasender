<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class PlatformRole extends Model
{
    use HasUuid;

    protected $fillable = ['uuid', 'slug', 'name', 'description', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(PlatformPermission::class, 'platform_permission_role');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'platform_role_user')->withTimestamps();
    }
}
