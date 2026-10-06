<?php

namespace App\Data\Templates;

final readonly class TemplateParseResult
{
    public function __construct(public ?string $body, public ?string $caption, public array $variables, public array $errors) {}

    public function valid(): bool
    {
        return $this->errors === [];
    }
}
