<?php

namespace Tests\Feature;

use App\Data\Campaigns\CampaignControlData;
use App\Enums\WhatsAppCampaignExecutionStatus;
use App\Enums\WhatsAppCampaignRecipientExecutionStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignDispatchAttempt;
use App\Models\WhatsAppCampaignExecution;
use App\Models\WhatsAppCampaignPreparation;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppCampaignRecipientExecution;
use App\Models\WhatsAppCampaignUsageReservation;
use App\Models\WhatsAppSession;
use App\Services\Campaigns\CancelWhatsAppCampaignExecutionService;
use App\Services\Campaigns\ProcessWhatsAppCampaignConnectorEventService;
use App\Services\Campaigns\ReconcileWhatsAppCampaignExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class WhatsAppCampaignCriticalTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_and_out_of_order_callbacks_consume_usage_once_and_never_downgrade_read(): void
    {
        $graph = $this->graph();
        $processor = app(ProcessWhatsAppCampaignConnectorEventService::class);
        $sent = $this->event($graph, 'campaign.transport.sent', 'sent-event');
        $this->assertFalse($processor->process($sent, hash('sha256', 'sent')));
        $this->assertTrue($processor->process($sent, hash('sha256', 'sent')));
        $processor->process($this->event($graph, 'campaign.message.read', 'read-event'), hash('sha256', 'read'));
        $processor->process($this->event($graph, 'campaign.message.delivered', 'delivered-event'), hash('sha256', 'delivered'));

        $recipient = $graph['recipientExecution']->refresh();
        $this->assertSame(WhatsAppCampaignRecipientExecutionStatus::Read, $recipient->status);
        $this->assertSame(1, $graph['reservation']->refresh()->consumed_units);
        $this->assertSame(1, $graph['execution']->refresh()->sent_recipients);
        $this->assertSame(1, $graph['execution']->delivered_recipients);
        $this->assertSame(1, $graph['execution']->read_recipients);
    }

    public function test_cross_tenant_callback_correlation_is_rejected(): void
    {
        $graph = $this->graph();
        $event = $this->event($graph, 'campaign.transport.sent', 'foreign-event');
        $event['tenant_uuid'] = Tenant::factory()->create()->uuid;
        $this->expectException(NotFoundHttpException::class);
        app(ProcessWhatsAppCampaignConnectorEventService::class)->process($event, hash('sha256', 'foreign'));
    }

    public function test_execution_reconciliation_treats_read_as_sent_and_delivered(): void
    {
        $graph = $this->graph();
        $graph['recipientExecution']->forceFill(['status' => WhatsAppCampaignRecipientExecutionStatus::Read, 'sent_at' => now(), 'delivered_at' => now(), 'read_at' => now()])->save();
        app(ReconcileWhatsAppCampaignExecutionService::class)->reconcile($graph['execution']);

        $execution = $graph['execution']->refresh();
        $this->assertSame(1, $execution->sent_recipients);
        $this->assertSame(1, $execution->delivered_recipients);
        $this->assertSame(1, $execution->read_recipients);
        $this->assertEquals(100, $execution->progress_percentage);
    }

    public function test_cancellation_preserves_confirmed_send_and_releases_only_unsent_capacity(): void
    {
        $graph = $this->graph(2);
        $graph['recipientExecution']->forceFill(['status' => WhatsAppCampaignRecipientExecutionStatus::Sent, 'sent_at' => now(), 'usage_consumed_at' => now()])->save();
        $graph['attempt']->forceFill(['status' => 'succeeded'])->save();
        $graph['reservation']->forceFill(['consumed_units' => 1])->save();
        $secondSnapshot = $this->snapshot($graph['campaign'], $graph['preparation'], '+15550100002');
        $second = $this->recipientExecution($graph['execution'], $secondSnapshot);
        $second->forceFill(['status' => WhatsAppCampaignRecipientExecutionStatus::Pending, 'attempt_count' => 0])->save();

        app(CancelWhatsAppCampaignExecutionService::class)->cancel(
            $graph['campaign'],
            new CampaignControlData((string) Str::uuid(), $graph['campaign']->version),
            $graph['actor'],
        );

        $this->assertSame(WhatsAppCampaignRecipientExecutionStatus::Sent, $graph['recipientExecution']->refresh()->status);
        $this->assertSame(WhatsAppCampaignRecipientExecutionStatus::Cancelled, $second->refresh()->status);
        $reservation = $graph['reservation']->refresh();
        $this->assertSame(1, $reservation->consumed_units);
        $this->assertSame(1, $reservation->released_units);
    }

    public function test_cancellation_waits_for_transport_pending_recipient_before_releasing_usage(): void
    {
        $graph = $this->graph();

        app(CancelWhatsAppCampaignExecutionService::class)->cancel(
            $graph['campaign'],
            new CampaignControlData((string) Str::uuid(), $graph['campaign']->version),
            $graph['actor'],
        );

        $this->assertSame(WhatsAppCampaignExecutionStatus::Cancelling, $graph['execution']->refresh()->status);
        $this->assertSame(WhatsAppCampaignStatus::Cancelling, $graph['campaign']->refresh()->status);
        $this->assertSame(WhatsAppCampaignRecipientExecutionStatus::TransportPending, $graph['recipientExecution']->refresh()->status);
        $this->assertSame(0, $graph['reservation']->refresh()->released_units);
    }

    private function graph(int $units = 1): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create();
        $hash = hash('sha256', 'campaign-payload');
        $campaign = WhatsAppCampaign::forceCreate([
            'tenant_id' => $tenant->id, 'name' => 'Critical campaign', 'status' => WhatsAppCampaignStatus::Running,
            'message_type' => 'text', 'body' => 'Test only', 'audience_type' => 'all_eligible_contacts',
            'session_strategy' => 'automatic_pool', 'schedule_type' => 'send_now', 'payload_hash' => $hash,
            'version' => 1, 'eligible_recipient_count' => $units,
        ]);
        $preparation = WhatsAppCampaignPreparation::forceCreate([
            'tenant_id' => $tenant->id, 'whatsapp_campaign_id' => $campaign->id, 'status' => 'completed',
            'campaign_version' => 1, 'campaign_payload_hash' => $hash, 'audience_type' => 'all_eligible_contacts',
            'audience_definition_hash' => hash('sha256', 'audience'), 'idempotency_key' => (string) Str::uuid(),
            'eligible_count' => $units, 'completed_at' => now(),
        ]);
        $reservation = WhatsAppCampaignUsageReservation::forceCreate([
            'tenant_id' => $tenant->id, 'status' => 'reserved', 'reserved_units' => $units,
            'consumed_units' => 0, 'released_units' => 0, 'idempotency_key' => (string) Str::uuid(),
        ]);
        $execution = WhatsAppCampaignExecution::forceCreate([
            'tenant_id' => $tenant->id, 'whatsapp_campaign_id' => $campaign->id, 'preparation_id' => $preparation->id,
            'usage_reservation_id' => $reservation->id, 'status' => WhatsAppCampaignExecutionStatus::Running,
            'campaign_version' => 1, 'campaign_payload_hash' => $hash, 'execution_config_hash' => hash('sha256', 'config'),
            'idempotency_key' => (string) Str::uuid(), 'launch_type' => 'manual', 'total_recipients' => $units,
            'pending_recipients' => $units, 'started_at' => now(),
        ]);
        $session = WhatsAppSession::forceCreate([
            'tenant_id' => $tenant->id, 'name' => 'Test session', 'storage_key' => 'test_'.Str::lower(Str::random(40)),
            'status' => 'ready', 'created_by' => $actor->id, 'ready_at' => now(),
        ]);
        $snapshot = $this->snapshot($campaign, $preparation, '+15550100001');
        $recipientExecution = $this->recipientExecution($execution, $snapshot, $session);
        $attempt = WhatsAppCampaignDispatchAttempt::forceCreate([
            'tenant_id' => $tenant->id, 'whatsapp_campaign_id' => $campaign->id, 'campaign_execution_id' => $execution->id,
            'campaign_recipient_id' => $snapshot->id, 'recipient_execution_id' => $recipientExecution->id,
            'session_id' => $session->id, 'attempt_number' => 1, 'status' => 'transport_pending',
            'idempotency_key' => hash('sha256', (string) Str::uuid()), 'transport_request_hash' => hash('sha256', 'request'),
            'transport_requested_at' => now(),
        ]);
        $campaign->forceFill(['active_preparation_id' => $preparation->id, 'active_execution_id' => $execution->id])->save();

        return compact('tenant', 'actor', 'campaign', 'preparation', 'reservation', 'execution', 'session', 'snapshot', 'recipientExecution', 'attempt');
    }

    private function snapshot(WhatsAppCampaign $campaign, WhatsAppCampaignPreparation $preparation, string $phone): WhatsAppCampaignRecipient
    {
        return WhatsAppCampaignRecipient::forceCreate([
            'tenant_id' => $campaign->tenant_id, 'whatsapp_campaign_id' => $campaign->id, 'preparation_id' => $preparation->id,
            'contact_uuid' => (string) Str::uuid(), 'phone_normalized' => $phone, 'whatsapp_address' => ltrim($phone, '+').'@c.us',
            'consent_status' => 'granted', 'recipient_status' => 'prepared', 'source_type' => 'manual_contacts',
            'deduplication_key' => hash('sha256', $phone), 'campaign_version' => 1,
            'campaign_payload_hash' => $campaign->payload_hash, 'prepared_at' => now(),
        ]);
    }

    private function recipientExecution(WhatsAppCampaignExecution $execution, WhatsAppCampaignRecipient $snapshot, ?WhatsAppSession $session = null): WhatsAppCampaignRecipientExecution
    {
        return WhatsAppCampaignRecipientExecution::forceCreate([
            'tenant_id' => $execution->tenant_id, 'whatsapp_campaign_id' => $execution->whatsapp_campaign_id,
            'campaign_execution_id' => $execution->id, 'campaign_recipient_id' => $snapshot->id, 'session_id' => $session?->id,
            'status' => WhatsAppCampaignRecipientExecutionStatus::TransportPending, 'attempt_count' => 1, 'max_attempts' => 2,
            'idempotency_key' => $execution->uuid.':'.$snapshot->uuid,
        ]);
    }

    private function event(array $graph, string $type, string $eventId): array
    {
        return [
            'event_id' => $eventId, 'event_type' => $type, 'occurred_at' => now()->toIso8601String(),
            'tenant_uuid' => $graph['tenant']->uuid, 'campaign_uuid' => $graph['campaign']->uuid,
            'execution_uuid' => $graph['execution']->uuid, 'recipient_execution_uuid' => $graph['recipientExecution']->uuid,
            'dispatch_attempt_uuid' => $graph['attempt']->uuid, 'session_uuid' => $graph['session']->uuid,
            'idempotency_key' => $graph['attempt']->idempotency_key, 'transport_reference' => 'transport-test',
            'whatsapp_message_id' => 'test-message-id',
        ];
    }
}
