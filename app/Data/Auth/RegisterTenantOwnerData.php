<?php

declare(strict_types=1);

namespace App\Data\Auth;

final readonly class RegisterTenantOwnerData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $companyName,
        public string $password,
    ) {}
}
