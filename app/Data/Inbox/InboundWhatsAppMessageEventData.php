<?php

namespace App\Data\Inbox;

use Carbon\CarbonImmutable;

final readonly class InboundWhatsAppMessageEventData
{
    public function __construct(public string $eventId, public string $tenantUuid, public string $sessionUuid, public CarbonImmutable $occurredAt, public InboundWhatsAppMessageData $message, public string $payloadHash) {}
}
