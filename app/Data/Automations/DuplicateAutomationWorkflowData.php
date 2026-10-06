<?php

namespace App\Data\Automations;

final readonly class DuplicateAutomationWorkflowData
{
    public function __construct(public string $name) {}

    public static function from(array $v): self
    {
        return new self($v['name']);
    }
}
