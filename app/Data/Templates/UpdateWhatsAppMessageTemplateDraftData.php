<?php

namespace App\Data\Templates;

final readonly class UpdateWhatsAppMessageTemplateDraftData
{
    public function __construct(public string $name, public ?string $description, public string $type, public ?string $body, public ?string $caption, public int $expectedVersion, public string $variableContext = 'contact', public array $variableConfiguration = []) {}

    public static function from(array $data): self
    {
        return new self($data['name'], $data['description'] ?? null, $data['type'], $data['body'] ?? null, $data['caption'] ?? null, $data['expected_version'], $data['variable_context'] ?? 'contact', $data['variable_configuration'] ?? []);
    }
}
