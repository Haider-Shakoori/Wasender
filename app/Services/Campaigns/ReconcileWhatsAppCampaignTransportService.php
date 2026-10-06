<?php

namespace App\Services\Campaigns;

use App\Enums\WhatsAppCampaignDispatchAttemptStatus;
use App\Models\WhatsAppCampaignDispatchAttempt;
use Illuminate\Support\Facades\DB;

final class ReconcileWhatsAppCampaignTransportService
{
    public function __construct(
        private NodeWhatsAppCampaignClient $client,
        private ProcessWhatsAppCampaignConnectorEventService $processor,
    ) {}

    public function reconcile(int $limit): array
    {
        $stats = ['checked' => 0, 'resolved' => 0, 'unresolved' => 0];
        $attempts = WhatsAppCampaignDispatchAttempt::with(['recipientExecution.execution.tenant'])
            ->whereIn('status', [WhatsAppCampaignDispatchAttemptStatus::Unknown, WhatsAppCampaignDispatchAttemptStatus::TransportPending])
            ->where('transport_requested_at', '<=', now()->subMinute())
            ->orderBy('transport_requested_at')
            ->limit($limit)->get();
        foreach ($attempts as $attempt) {
            $stats['checked']++;
            $result = $this->client->lookup($attempt->uuid, $attempt->recipientExecution->execution->tenant->uuid, $attempt->idempotency_key);
            $attempt->forceFill(['last_reconciled_at' => now()])->save();
            if (in_array($result->status, ['sent', 'accepted'], true) && $result->whatsappMessageId) {
                $this->apply($attempt->id, [
                    'event_type' => 'campaign.transport.sent', 'transport_reference' => $result->reference,
                    'whatsapp_message_id' => $result->whatsappMessageId,
                ]);
                $stats['resolved']++;
            } elseif ($result->status === 'failed') {
                $this->apply($attempt->id, [
                    'event_type' => 'campaign.transport.failed',
                    'failure' => ['class' => $result->failureClass, 'code' => $result->failureCode, 'retryable' => $result->retryable, 'message' => 'The connector confirmed that the prior attempt did not send.'],
                ]);
                $stats['resolved']++;
            } elseif ($attempt->transport_requested_at?->lte(now()->subMinutes(config('whatsapp_campaign_transport.unknown_timeout_minutes')))) {
                $this->apply($attempt->id, [
                    'event_type' => 'campaign.transport.failed',
                    'failure' => ['class' => 'unknown', 'code' => 'dispatch_lookup_failed', 'retryable' => false, 'message' => 'The uncertain dispatch could not be resolved within the reconciliation window.'],
                ]);
                $stats['resolved']++;
            } else {
                DB::transaction(function () use ($attempt): void {
                    $locked = WhatsAppCampaignDispatchAttempt::with(['recipientExecution.execution.campaign', 'session'])->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                    $this->processor->apply($locked, ['event_type' => 'campaign.transport.unknown']);
                }, 3);
                $stats['unresolved']++;
            }
        }

        return $stats;
    }

    private function apply(int $attemptId, array $event): void
    {
        DB::transaction(function () use ($attemptId, $event): void {
            $attempt = WhatsAppCampaignDispatchAttempt::with(['recipientExecution.execution.campaign', 'session'])->whereKey($attemptId)->lockForUpdate()->firstOrFail();
            $this->processor->apply($attempt, $event);
        }, 3);
    }
}
