<?php

namespace App\Data\Inbox;

use Carbon\CarbonImmutable;

final readonly class InboundWhatsAppMessageData
{
    public function __construct(public string $whatsappMessageId, public ?string $serializedId, public string $from, public string $to, public string $type, public ?string $body, public ?string $caption, public ?string $replyToMessageId, public CarbonImmutable $timestamp, public ?InboundWhatsAppMediaData $media) {}
}
