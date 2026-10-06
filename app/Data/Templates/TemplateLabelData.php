<?php

namespace App\Data\Templates;

final readonly class TemplateLabelData
{
    public function __construct(public string $name, public ?string $color = null) {}

    public static function from(array $data): self
    {
        return new self($data['name'], $data['color'] ?? null);
    }
}
