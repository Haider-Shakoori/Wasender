<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

final readonly class RegistrationResult
{
    public function __construct(
        public User $user,
        public Tenant $tenant,
        public TenantMembership $membership,
    ) {}
}
