<?php

namespace App\Data\Campaigns;

final readonly class CampaignValidationResult
{
    public function __construct(public array $errors = [], public array $warnings = []) {}

    public function valid(): bool
    {
        return $this->errors === [];
    }
}
