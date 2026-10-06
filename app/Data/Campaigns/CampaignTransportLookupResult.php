<?php

namespace App\Data\Campaigns;

final readonly class CampaignTransportLookupResult
{
    public function __construct(
        public string $status,
        public ?string $reference = null,
        public ?string $whatsappMessageId = null,
        public ?string $failureClass = null,
        public ?string $failureCode = null,
        public bool $retryable = false,
    ) {}
}
