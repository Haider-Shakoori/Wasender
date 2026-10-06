<?php

declare(strict_types=1);

namespace App\Data\Tenancy;

use App\Enums\ActiveTenantResolutionStatus;
use App\Models\Tenant;

final readonly class ActiveTenantResolution
{
    public function __construct(
        public ActiveTenantResolutionStatus $status,
        public ?Tenant $tenant = null,
        public ?string $source = null,
    ) {}
}
