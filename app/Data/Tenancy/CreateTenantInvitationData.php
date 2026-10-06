<?php

declare(strict_types=1);

namespace App\Data\Tenancy;

final readonly class CreateTenantInvitationData
{
    public function __construct(public string $email, public int $roleId) {}
}
