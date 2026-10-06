<?php

declare(strict_types=1);

namespace App\Data\Messaging;

final readonly class MessageData
{
    public function __construct(
        public string $messageReference,
        public string $sessionReference,
        public string $storageKey,
        public string $requestId,
        public string $recipient,
        public string $type,
        public ?string $body,
        public ?array $media = null,
        public ?string $expiresAt = null,
    ) {}
}
