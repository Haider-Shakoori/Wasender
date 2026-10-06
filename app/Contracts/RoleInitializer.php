<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Support\Collection;

interface RoleInitializer
{
    /** @return Collection<string, Role> */
    public function initializeForTenant(Tenant $tenant): Collection;
}
