<?php

namespace App\Data\Inbox;

final readonly class InboxMessageProcessingResult
{
    public function __construct(public string $conversationUuid, public string $messageUuid, public bool $duplicate = false) {}

    public function toArray(): array
    {
        return ['accepted' => true, 'duplicate' => $this->duplicate, 'conversation_uuid' => $this->conversationUuid, 'message_uuid' => $this->messageUuid];
    }
}
