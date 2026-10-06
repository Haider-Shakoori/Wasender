<?php

declare(strict_types=1);

namespace App\Data\Messaging;

final readonly class SendMessageResult
{
    public function __construct(public bool $accepted, public ?string $externalId = null, public ?string $errorCode = null, public bool $retryable = false) {}
}
