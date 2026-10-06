<?php

namespace App\Data\Templates;

final readonly class RestoreWhatsAppMessageTemplateData
{
    public function __construct(public int $expectedVersion) {}

    public static function from(array $data): self
    {
        return new self($data['expected_version']);
    }
}
