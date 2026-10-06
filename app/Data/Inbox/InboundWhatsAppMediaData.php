<?php

namespace App\Data\Inbox;

final readonly class InboundWhatsAppMediaData
{
    public function __construct(public string $mimeType, public int $sizeBytes, public string $checksumSha256, public string $retrievalReference) {}
}
