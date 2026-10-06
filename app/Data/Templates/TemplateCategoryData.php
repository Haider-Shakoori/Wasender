<?php

namespace App\Data\Templates;

final readonly class TemplateCategoryData
{
    public function __construct(public string $name, public ?string $description = null) {}

    public static function from(array $data): self
    {
        return new self($data['name'], $data['description'] ?? null);
    }
}
