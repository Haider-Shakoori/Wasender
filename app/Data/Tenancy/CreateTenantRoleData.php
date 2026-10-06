<?php

declare(strict_types=1);

namespace App\Data\Tenancy;

final readonly class CreateTenantRoleData
{
    public function __construct(public string $name, public ?string $description, public array $permissionIds) {}
}
