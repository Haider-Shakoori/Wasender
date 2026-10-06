<?php

namespace App\Data\Subscriptions;

final readonly class EntitlementLimit
{
    public function __construct(public ?int $value, public bool $unlimited = false) {}

    public function remaining(int $usage): ?int
    {
        return $this->unlimited ? null : max(0, ($this->value ?? 0) - $usage);
    }

    public function permits(int $usage, int $amount = 1): bool
    {
        return $this->unlimited || $usage + $amount <= ($this->value ?? 0);
    }
}
