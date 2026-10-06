<?php

namespace App\Data\Campaigns;

final readonly class CreateWhatsAppCampaignData
{
    public function __construct(public string $name, public ?string $description, public string $messageType, public ?string $body, public string $audienceType, public array $audienceConfig, public string $sessionStrategy, public array $sessionUuids, public string $scheduleType, public ?string $scheduledAtLocal, public ?string $timezone, public array $sendWindow, public array $execution, public string $idempotencyKey) {}

    public static function from(array $v): self
    {
        return new self($v['name'], $v['description'] ?? null, $v['message_type'], $v['body'] ?? null, $v['audience_type'], $v['audience_config'] ?? [], $v['session_strategy'], $v['session_uuids'] ?? [], $v['schedule_type'] ?? 'send_now', $v['scheduled_at_local'] ?? null, $v['timezone'] ?? null, $v['send_window'] ?? [], $v['execution'] ?? [], $v['idempotency_key']);
    }
}
