<?php

namespace App\Services\Campaigns;

use App\Enums\WhatsAppCampaignDispatchAttemptStatus;
use App\Enums\WhatsAppCampaignRecipientExecutionStatus;
use App\Models\Tenant;
use App\Models\WhatsAppCampaignConnectorEvent;
use App\Models\WhatsAppCampaignDispatchAttempt;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProcessWhatsAppCampaignConnectorEventService
{
    public function __construct(
        private CampaignUsageReservationService $usage,
        private FinalizeWhatsAppCampaignExecutionService $finalize,
    ) {}

    public function process(array $data, string $payloadHash): bool
    {
        $existing = WhatsAppCampaignConnectorEvent::where('event_id', $data['event_id'])->first();
        if ($existing) {
            throw_unless(hash_equals($existing->payload_hash, $payloadHash), ConflictHttpException::class, 'Connector event payload conflict.');

            return true;
        }

        try {
            DB::transaction(function () use ($data, $payloadHash): void {
                $tenant = Tenant::where('uuid', $data['tenant_uuid'])->first();
                $attempt = WhatsAppCampaignDispatchAttempt::with(['recipientExecution.execution.campaign', 'session'])
                    ->where('uuid', $data['dispatch_attempt_uuid'])->lockForUpdate()->first();
                if (! $tenant || ! $attempt || $attempt->tenant_id !== $tenant->id) {
                    throw new NotFoundHttpException;
                }
                $recipient = $attempt->recipientExecution;
                $execution = $recipient->execution;
                throw_unless(
                    $attempt->idempotency_key === $data['idempotency_key']
                    && $attempt->session?->uuid === $data['session_uuid']
                    && (! isset($data['campaign_uuid']) || $execution->campaign->uuid === $data['campaign_uuid'])
                    && (! isset($data['execution_uuid']) || $execution->uuid === $data['execution_uuid'])
                    && (! isset($data['recipient_execution_uuid']) || $recipient->uuid === $data['recipient_execution_uuid']),
                    NotFoundHttpException::class
                );
                $event = WhatsAppCampaignConnectorEvent::create([
                    'event_id' => $data['event_id'], 'event_type' => $data['event_type'], 'payload_hash' => $payloadHash,
                    'tenant_id' => $tenant->id, 'dispatch_attempt_id' => $attempt->id, 'status' => 'processing',
                ]);
                $this->apply($attempt, $data);
                $event->forceFill(['status' => 'processed', 'processed_at' => now()])->save();
            }, 3);
        } catch (QueryException $e) {
            $duplicate = WhatsAppCampaignConnectorEvent::where('event_id', $data['event_id'])->first();
            if (! $duplicate || ! hash_equals($duplicate->payload_hash, $payloadHash)) {
                throw $e;
            }

            return true;
        }

        return false;
    }

    public function apply(WhatsAppCampaignDispatchAttempt $attempt, array $data): void
    {
        $recipient = $attempt->recipientExecution;
        $execution = $recipient->execution;
        $type = $data['event_type'];
        if (in_array($type, ['campaign.transport.sent', 'campaign.message.delivered', 'campaign.message.read'], true)) {
            $messageId = $data['whatsapp_message_id'] ?? $attempt->whatsapp_message_id;
            throw_unless(is_string($messageId) && $messageId !== '', ConflictHttpException::class, 'Missing authoritative message correlation.');
            $attempt->forceFill([
                'status' => WhatsAppCampaignDispatchAttemptStatus::Succeeded,
                'transport_reference' => $data['transport_reference'] ?? $attempt->transport_reference,
                'whatsapp_message_id' => $messageId,
                'transport_accepted_at' => $attempt->transport_accepted_at ?? now(),
                'succeeded_at' => $attempt->succeeded_at ?? now(),
                'failure_class' => null, 'failure_code' => null, 'failure_message' => null, 'unknown_since' => null,
            ])->save();
            $target = match ($type) {
                'campaign.message.read' => WhatsAppCampaignRecipientExecutionStatus::Read,
                'campaign.message.delivered' => WhatsAppCampaignRecipientExecutionStatus::Delivered,
                default => WhatsAppCampaignRecipientExecutionStatus::Sent,
            };
            if ($this->rank($target) > $this->rank($recipient->status) || in_array($recipient->status, [WhatsAppCampaignRecipientExecutionStatus::TransportPending, WhatsAppCampaignRecipientExecutionStatus::Processing, WhatsAppCampaignRecipientExecutionStatus::RetryScheduled, WhatsAppCampaignRecipientExecutionStatus::Cancelled], true)) {
                $recipient->forceFill([
                    'status' => $target,
                    'transport_reference' => $attempt->transport_reference,
                    'whatsapp_message_id' => $messageId,
                    'sent_at' => $recipient->sent_at ?? now(),
                    'delivered_at' => in_array($target, [WhatsAppCampaignRecipientExecutionStatus::Delivered, WhatsAppCampaignRecipientExecutionStatus::Read], true) ? ($recipient->delivered_at ?? now()) : $recipient->delivered_at,
                    'read_at' => $target === WhatsAppCampaignRecipientExecutionStatus::Read ? ($recipient->read_at ?? now()) : $recipient->read_at,
                    'failure_code' => null, 'failure_message' => null, 'last_connector_event_at' => now(),
                ])->save();
            }
            $this->usage->consumeForRecipient($recipient);
        } elseif ($type === 'campaign.transport.unknown') {
            $attempt->forceFill(['status' => WhatsAppCampaignDispatchAttemptStatus::Unknown, 'unknown_since' => $attempt->unknown_since ?? now(), 'failure_class' => 'unknown', 'failure_code' => 'transport_state_unknown'])->save();
            $recipient->forceFill(['status' => WhatsAppCampaignRecipientExecutionStatus::TransportPending, 'failure_code' => 'transport_state_unknown', 'last_connector_event_at' => now()])->save();
        } elseif (in_array($type, ['campaign.transport.failed', 'campaign.message.failed'], true)) {
            $retryable = (bool) data_get($data, 'failure.retryable', false);
            $canRetry = $retryable && $recipient->attempt_count < $recipient->max_attempts;
            $attempt->forceFill([
                'status' => $canRetry ? WhatsAppCampaignDispatchAttemptStatus::FailedTransient : WhatsAppCampaignDispatchAttemptStatus::FailedPermanent,
                'failed_at' => now(), 'failure_class' => data_get($data, 'failure.class', 'transport'),
                'failure_code' => data_get($data, 'failure.code', 'transport_rejected'),
                'failure_message' => data_get($data, 'failure.message', 'The connector rejected the dispatch.'),
            ])->save();
            $recipient->forceFill([
                'status' => $canRetry ? WhatsAppCampaignRecipientExecutionStatus::RetryScheduled : WhatsAppCampaignRecipientExecutionStatus::Failed,
                'next_attempt_at' => $canRetry ? now()->addSeconds(config('whatsapp_campaign_execution.retry_base_seconds')) : null,
                'failed_at' => $canRetry ? null : now(),
                'failure_code' => $attempt->failure_code, 'failure_message' => $attempt->failure_message, 'last_connector_event_at' => now(),
            ])->save();
            if (! $canRetry && $execution->reservation) {
                $this->usage->release($execution->reservation, 1);
            }
        }
        $this->recount($execution);
        $this->finalize->finalize($execution->refresh());
    }

    private function recount($execution): void
    {
        $counts = $execution->recipients()->selectRaw('status, count(*) total')->groupBy('status')->pluck('total', 'status');
        $sent = (int) (($counts['sent'] ?? 0) + ($counts['delivered'] ?? 0) + ($counts['read'] ?? 0));
        $delivered = (int) (($counts['delivered'] ?? 0) + ($counts['read'] ?? 0));
        $terminal = $sent + (int) ($counts['failed'] ?? 0) + (int) ($counts['skipped'] ?? 0) + (int) ($counts['cancelled'] ?? 0);
        $execution->forceFill([
            'processing_recipients' => (int) ($counts['processing'] ?? 0),
            'transport_pending_recipients' => (int) ($counts['transport_pending'] ?? 0),
            'retry_scheduled_recipients' => (int) ($counts['retry_scheduled'] ?? 0),
            'sent_recipients' => $sent, 'delivered_recipients' => $delivered, 'read_recipients' => (int) ($counts['read'] ?? 0),
            'failed_recipients' => (int) ($counts['failed'] ?? 0),
            'unknown_recipients' => $execution->attempts()->where('status', 'unknown')->count(),
            'progress_percentage' => $execution->total_recipients > 0 ? round($terminal * 100 / $execution->total_recipients, 2) : 100,
            'last_progress_at' => now(), 'last_connector_event_at' => now(), 'last_heartbeat_at' => now(),
        ])->save();
        $execution->campaign->forceFill([
            'sent_recipient_count' => $sent,
            'delivered_recipient_count' => $delivered,
            'read_recipient_count' => (int) ($counts['read'] ?? 0),
            'failed_recipient_count' => (int) ($counts['failed'] ?? 0),
        ])->save();
    }

    private function rank(WhatsAppCampaignRecipientExecutionStatus $status): int
    {
        return match ($status) {
            WhatsAppCampaignRecipientExecutionStatus::Sent => 10,
            WhatsAppCampaignRecipientExecutionStatus::Delivered => 20,
            WhatsAppCampaignRecipientExecutionStatus::Read => 30,
            default => 0,
        };
    }
}
