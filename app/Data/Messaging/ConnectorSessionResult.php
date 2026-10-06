<?php

declare(strict_types=1);

namespace App\Data\Messaging;

final readonly class ConnectorSessionResult
{
    public function __construct(public string $reference, public string $status, public array $metadata = []) {}
}
