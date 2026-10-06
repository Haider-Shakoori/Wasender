<?php

declare(strict_types=1);

namespace App\Data\Messaging;

use DateTimeImmutable;

final readonly class QrCodeResult
{
    public function __construct(public string $data, public DateTimeImmutable $expiresAt) {}
}
