<?php

namespace App\Jobs;

use App\Contracts\TenantContext;
use App\Contracts\WhatsAppCampaignTransport;
use App\Enums\WhatsAppCampaignDispatchAttemptStatus;
use App\Enums\WhatsAppCampaignExecutionStatus;
use App\Enums\WhatsAppCampaignRecipientExecutionStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\Contact;
use App\Models\WhatsAppCampaignDispatchAttempt;
use App\Models\WhatsAppCampaignRecipientExecution;
use App\Models\WhatsAppSession;
use App\Services\Campaigns\CampaignRecipientEligibilityService;
use App\Services\Campaigns\CampaignSessionSelector;
use App\Services\Campaigns\CampaignTransportRequestBuilder;
use App\Services\Campaigns\ProcessWhatsAppCampaignConnectorEventService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;

final class ProcessWhatsAppCampaignRecipient implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $recipientExecutionId)
    {
        $this->onQueue(config('whatsapp_campaign_execution.queues.dispatch'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("wa-recipient-execution:{$this->recipientExecutionId}"))->expireAfter(120)];
    }

    public function handle(TenantContext $context, CampaignRecipientEligibilityService $eligibility, CampaignSessionSelector $selector, CampaignTransportRequestBuilder $builder, WhatsAppCampaignTransport $transport, ProcessWhatsAppCampaignConnectorEventService $processor): void
    {
        $row = WhatsAppCampaignRecipientExecution::with(['execution.tenant', 'execution.campaign.activePreparation', 'execution.campaign.attachment', 'recipient.contact'])->findOrFail($this->recipientExecutionId);
        $context->set($row->execution->tenant);
        $executionId = $row->campaign_execution_id;
        try {
            if ($row->status !== WhatsAppCampaignRecipientExecutionStatus::Claimed) {
                return;
            }$execution = $row->execution;
            $campaign = $execution->campaign;
            if ($execution->status !== WhatsAppCampaignExecutionStatus::Running || $campaign->status !== WhatsAppCampaignStatus::Running) {
                return;
            }
            if ($execution->campaign_version !== $campaign->version || ! hash_equals($execution->campaign_payload_hash, $campaign->payload_hash) || $campaign->active_preparation_id !== $execution->preparation_id) {
                $this->skip($row, 'campaign_stale');

                return;
            }
            $contact = Contact::withTrashed()->where('tenant_id', $row->tenant_id)->whereKey($row->recipient->contact_id)->first();
            if (! $contact) {
                $this->skip($row, 'contact_missing');

                return;
            }
            $decision = $eligibility->evaluate($contact, $campaign);
            if (! $decision->eligible) {
                $code = match ($decision->primaryReason) {
                    'opted_out' => 'opted_out_after_preparation','suppressed' => 'suppressed_after_preparation','blocked' => 'blocked_after_preparation',default => 'consent_changed'
                };
                $this->skip($row, $code);

                return;
            }
            $session = $selector->select($execution);
            if (! $session) {
                $row->forceFill(['status' => WhatsAppCampaignRecipientExecutionStatus::RetryScheduled, 'next_attempt_at' => now()->addSeconds(config('whatsapp_campaign_execution.retry_base_seconds')), 'failure_code' => 'session_unavailable', 'failure_message' => 'No ready session capacity is currently available.'])->save();

                return;
            }
            $attempt = DB::transaction(function () use ($row, $session) {
                WhatsAppSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
                $inflight = WhatsAppCampaignRecipientExecution::where('session_id', $session->id)->whereIn('status', ['processing'])->count();
                if ($inflight >= config('whatsapp_campaign_execution.session_concurrency')) {
                    return null;
                }$row->forceFill(['status' => WhatsAppCampaignRecipientExecutionStatus::Processing, 'session_id' => $session->id, 'processing_started_at' => now(), 'last_attempt_at' => now(), 'attempt_count' => $row->attempt_count + 1])->save();
                $number = $row->attempt_count;
                $key = hash('sha256', $row->execution->uuid.'|'.$row->uuid.'|'.$number.'|'.$row->execution->campaign_payload_hash);
                $existing = WhatsAppCampaignDispatchAttempt::where('idempotency_key', $key)->first();
                if ($existing) {
                    return $existing;
                }$attempt = new WhatsAppCampaignDispatchAttempt;
                $attempt->forceFill(['tenant_id' => $row->tenant_id, 'whatsapp_campaign_id' => $row->whatsapp_campaign_id, 'campaign_execution_id' => $row->campaign_execution_id, 'campaign_recipient_id' => $row->campaign_recipient_id, 'recipient_execution_id' => $row->id, 'session_id' => $session->id, 'attempt_number' => $number, 'status' => WhatsAppCampaignDispatchAttemptStatus::Created, 'idempotency_key' => $key, 'transport_request_hash' => str_repeat('0', 64), 'queued_at' => now(), 'started_at' => now()])->save();

                return $attempt;
            });
            if (! $attempt) {
                $row->forceFill(['status' => WhatsAppCampaignRecipientExecutionStatus::RetryScheduled, 'next_attempt_at' => now()->addSeconds(config('whatsapp_campaign_execution.retry_base_seconds'))])->save();

                return;
            }
            $request = $builder->build($row->refresh(), $attempt, $session);
            $attempt->forceFill(['transport_request_hash' => $builder->hash($request), 'status' => WhatsAppCampaignDispatchAttemptStatus::TransportPending, 'transport_requested_at' => now()])->save();
            $result = $transport->dispatch($request);
            if (! $result->available) {
                $row->forceFill(['status' => WhatsAppCampaignRecipientExecutionStatus::TransportPending, 'failure_code' => 'transport_unavailable', 'failure_message' => 'Recipient dispatch is ready, but campaign transport is not active until Part 4.'])->save();
                $execution->increment('transport_pending_recipients');
                $execution->forceFill(['processing_recipients' => max(0, $execution->processing_recipients - 1), 'last_progress_at' => now(), 'last_heartbeat_at' => now()])->save();

                return;
            }
            DB::transaction(function () use ($attempt, $result, $processor): void {
                $locked = WhatsAppCampaignDispatchAttempt::with(['recipientExecution.execution.campaign', 'session'])
                    ->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                $eventType = $result->accepted && $result->whatsappMessageId
                    ? 'campaign.transport.sent'
                    : ($result->status === 'unknown' ? 'campaign.transport.unknown' : 'campaign.transport.failed');
                $processor->apply($locked, [
                    'event_type' => $eventType,
                    'transport_reference' => $result->reference,
                    'whatsapp_message_id' => $result->whatsappMessageId,
                    'failure' => [
                        'class' => $result->failureClass ?? ($result->status === 'unknown' ? 'unknown' : 'transport'),
                        'code' => $result->failureCode,
                        'retryable' => $result->retryable,
                        'message' => $result->status === 'unknown'
                            ? 'The transport outcome is uncertain and will be reconciled without resending.'
                            : 'The connector could not dispatch this recipient.',
                    ],
                ]);
            }, 3);
        } finally {
            ContinueWhatsAppCampaignExecution::dispatch($executionId);
        }
    }

    private function skip(WhatsAppCampaignRecipientExecution $row, string $code): void
    {
        $row->forceFill(['status' => WhatsAppCampaignRecipientExecutionStatus::Skipped, 'skipped_at' => now(), 'failure_code' => $code, 'failure_message' => 'The recipient is no longer eligible under current consent and safety rules.'])->save();
        $row->execution->increment('skipped_recipients');
        $row->execution->forceFill(['processing_recipients' => max(0, $row->execution->processing_recipients - 1), 'last_progress_at' => now()])->save();
        $row->execution->campaign->increment('skipped_recipient_count');
    }
}
