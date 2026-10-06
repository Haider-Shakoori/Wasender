<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Role;
use App\Models\TenantMembership;
use Illuminate\Support\Collection;

interface TenantAuthorization
{
    public function allows(string $permission): bool;

    public function denies(string $permission): bool;

    public function require(string $permission): void;

    public function role(): Role;

    public function membership(): TenantMembership;

    /** @return Collection<int, string> */
    public function permissions(): Collection;
}
