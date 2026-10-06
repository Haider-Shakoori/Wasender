<?php

declare(strict_types=1);

namespace App\Data\Tenancy;

use App\Models\Tenant;
use App\Models\TenantMembership;

final readonly class TenantSwitchResult
{
    public function __construct(
        public Tenant $tenant,
        public TenantMembership $membership,
        public ?Tenant $previousTenant,
    ) {}
}
