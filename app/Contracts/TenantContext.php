<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Tenant;

interface TenantContext
{
    public function set(Tenant $tenant): void;

    public function get(): Tenant;

    public function id(): int;

    public function check(): bool;

    public function clear(): void;
}
