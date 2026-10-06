<?php

namespace App\Data\Templates;

final readonly class TemplateValidationResult
{
    public function __construct(public array $errors, public array $warnings, public array $variables, public ?string $contentHash = null) {}

    public function valid(): bool
    {
        return $this->errors === [];
    }
}
