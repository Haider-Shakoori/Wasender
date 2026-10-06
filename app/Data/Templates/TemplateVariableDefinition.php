<?php

namespace App\Data\Templates;

use App\Enums\TemplateVariableContext;

final readonly class TemplateVariableDefinition
{
    public function __construct(public string $key, public array $contexts, public string $previewValue) {}

    public function supports(TemplateVariableContext $context): bool
    {
        return in_array($context, $this->contexts, true);
    }
}
