<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantContext;
use App\Exceptions\TenantNotResolvedException;
use App\Models\Tenant;

final class TenantContextService implements TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): Tenant
    {
        return $this->tenant ?? throw new TenantNotResolvedException;
    }

    public function id(): int
    {
        return (int) $this->get()->getKey();
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
