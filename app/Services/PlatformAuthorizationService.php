<?php

namespace App\Services;

use App\Contracts\PlatformAuthorization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;

final class PlatformAuthorizationService implements PlatformAuthorization
{
    public function __construct(private readonly Repository $cache) {}

    public function allows(User $user, string $permission): bool
    {
        return $user->isActive() && $this->permissions($user)->contains($permission);
    }

    public function require(User $user, string $permission): void
    {
        throw_unless($this->allows($user, $permission), AuthorizationException::class);
    }

    public function permissions(User $user): Collection
    {
        if (! $user->uuid) {
            return collect();
        }

        return collect($this->cache->rememberForever($this->key($user), fn () => $user->platformRoles()->with('permissions:id,slug')->get()
            ->flatMap->permissions->pluck('slug')->unique()->sort()->values()->all()
        ));
    }

    public function forget(User $user): void
    {
        if ($user->uuid) {
            $this->cache->forget($this->key($user));
        }
    }

    private function key(User $user): string
    {
        return "platform-auth:user:{$user->uuid}:permissions";
    }
}
