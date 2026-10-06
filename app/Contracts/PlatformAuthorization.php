<?php

namespace App\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

interface PlatformAuthorization
{
    public function allows(User $user, string $permission): bool;

    public function require(User $user, string $permission): void;

    /** @return Collection<int, string> */
    public function permissions(User $user): Collection;

    public function forget(User $user): void;
}
