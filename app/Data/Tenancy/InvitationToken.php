<?php

declare(strict_types=1);

namespace App\Data\Tenancy;

final readonly class InvitationToken
{
    public function __construct(public string $plain, public string $hash) {}
}
