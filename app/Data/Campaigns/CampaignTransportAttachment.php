<?php

namespace App\Data\Campaigns;

final readonly class CampaignTransportAttachment
{
    public function __construct(public string $uuid, public string $mimeType, public string $filename, public int $sizeBytes, public string $checksumSha256, public string $retrievalToken, public string $retrievalUrl) {}
}
