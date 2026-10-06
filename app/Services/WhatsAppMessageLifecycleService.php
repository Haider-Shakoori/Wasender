<?php

namespace App\Services;

use App\Enums\WhatsAppInboxMessageStatus;
use App\Events\WhatsAppMessageStatusChanged;
use App\Enums\WhatsAppMessageStatus as Status;
use App\Models\WhatsAppInboxMessage;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WhatsAppMessageLifecycleService
{
    private const ALLOWED = [
        'queued' => ['processing', 'cancelled', 'expired', 'failed'],
        'processing' => ['sending', 'queued', 'failed', 'expired', 'cancelled'],
        'sending' => ['sent', 'failed', 'queued'],
        'sent' => ['delivered', 'read'],
        'delivered' => ['read'],
        'failed' => ['queued', 'sent'],
    ];

    public function transition(WhatsAppMessage $message, Status $to, string $source, array $changes = [], ?string $eventUuid = null): WhatsAppMessage
    {
        $result = DB::transaction(function () use ($message, $to, $source, $changes, $eventUuid): array {
            $locked = WhatsAppMessage::lockForUpdate()->findOrFail($message->id);
            $from = $locked->status;
            if ($from === $to) {
                return ['message' => $locked, 'changed' => false];
            }
            if ($eventUuid && WhatsAppMessageEvent::where('event_uuid', $eventUuid)->exists()) {
                return ['message' => $locked, 'changed' => false];
            }
            if (! in_array($to->value, self::ALLOWED[$from->value] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Cannot transition {$from->value} to {$to->value}."]);
            }
            $timestamp = match ($to) {
                Status::Processing => 'processing_at', Status::Sending => 'sending_at', Status::Sent => 'sent_at',
                Status::Delivered => 'delivered_at', Status::Read => 'read_at', Status::Failed => 'failed_at',
                Status::Cancelled => 'cancelled_at', Status::Expired => 'expired_at', Status::Queued => 'queued_at',
            };
            $safe = collect($changes)->only(['connector_request_id', 'whatsapp_message_id', 'failure_code', 'failure_message', 'failure_retryable', 'next_retry_at'])->all();
            if (isset($safe['failure_message'])) {
                $safe['failure_message'] = str(strip_tags((string) $safe['failure_message']))->limit(500);
            }
            $locked->update(['status' => $to, $timestamp => now()] + $safe);
            $inboxStatus = match ($to) {
                Status::Queued => WhatsAppInboxMessageStatus::Queued,
                Status::Sent => WhatsAppInboxMessageStatus::Sent,
                Status::Delivered => WhatsAppInboxMessageStatus::Delivered,
                Status::Read => WhatsAppInboxMessageStatus::Read,
                Status::Failed, Status::Cancelled, Status::Expired => WhatsAppInboxMessageStatus::Failed,
                default => null,
            };
            if ($inboxStatus) {
                $inboxTimestamp = match ($inboxStatus) {
                    WhatsAppInboxMessageStatus::Sent => 'sent_at',
                    WhatsAppInboxMessageStatus::Delivered => 'delivered_at',
                    WhatsAppInboxMessageStatus::Read => 'read_at',
                    WhatsAppInboxMessageStatus::Failed => 'failed_at',
                    default => null,
                };
                $inboxChanges = [
                    'status' => $inboxStatus,
                    'whatsapp_message_id' => $locked->whatsapp_message_id,
                    'failure_code' => $safe['failure_code'] ?? null,
                ];
                if ($inboxTimestamp) {
                    $inboxChanges[$inboxTimestamp] = now();
                }
                WhatsAppInboxMessage::query()->where('outbound_message_id', $locked->id)->update($inboxChanges);
            }
            WhatsAppMessageEvent::create(['tenant_id' => $locked->tenant_id, 'whatsapp_message_id' => $locked->id, 'event' => 'status.changed',
                'from_status' => $from->value, 'to_status' => $to->value, 'source' => $source, 'event_uuid' => $eventUuid,
                'reason_code' => $safe['failure_code'] ?? null, 'message' => $safe['failure_message'] ?? null, 'occurred_at' => now(), 'created_at' => now()]);

            return ['message' => $locked->refresh(), 'changed' => true];
        }, 3);

        if ($result['changed']) {
            event(new WhatsAppMessageStatusChanged($result['message']));
        }

        return $result['message'];
    }

    public function acknowledge(WhatsAppMessage $message, Status $target, string $eventUuid): WhatsAppMessage
    {
        $rank = [Status::Sent->value => 1, Status::Delivered->value => 2, Status::Read->value => 3];
        if (($rank[$target->value] ?? 0) <= ($rank[$message->status->value] ?? 0)) {
            return $message;
        }

        return $this->transition($message, $target, 'connector', [], $eventUuid);
    }
}
