<?php

namespace App\Data\Templates;

final readonly class CreateWhatsAppMessageTemplateData
{
    public function __construct(public string $name, public ?string $description, public string $type, public ?string $body, public ?string $caption, public string $variableContext = 'contact', public array $variableConfiguration = []) {}

    public static function from(array $data): self
    {
        return new self($data['name'], $data['description'] ?? null, $data['type'], $data['body'] ?? null, $data['caption'] ?? null, $data['variable_context'] ?? 'contact', $data['variable_configuration'] ?? []);
    }
}
