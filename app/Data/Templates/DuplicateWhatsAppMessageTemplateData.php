<?php

namespace App\Data\Templates;

final readonly class DuplicateWhatsAppMessageTemplateData
{
    public function __construct(public string $name, public bool $preferDraft = false) {}

    public static function from(array $data): self
    {
        return new self($data['name'], (bool) ($data['prefer_draft'] ?? false));
    }
}
