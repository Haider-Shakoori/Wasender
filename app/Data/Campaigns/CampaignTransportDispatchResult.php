<?php

namespace App\Data\Campaigns;

final readonly class CampaignTransportDispatchResult
{
    public function __construct(public bool $available, public bool $accepted = false, public ?string $reference = null, public ?string $failureCode = 'transport_unavailable', public bool $retryable = true, public string $status = 'failed', public ?string $whatsappMessageId = null, public ?string $failureClass = null) {}
}
