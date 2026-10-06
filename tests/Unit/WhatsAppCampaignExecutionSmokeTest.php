<?php

namespace Tests\Unit;

use App\Contracts\WhatsAppCampaignTransport;
use App\Enums\WhatsAppCampaignFailureClass;
use App\Enums\WhatsAppCampaignRecipientExecutionStatus;
use App\Jobs\ContinueWhatsAppCampaignExecution;
use App\Jobs\ProcessWhatsAppCampaignRecipient;
use App\Jobs\StartWhatsAppCampaignExecution;
use App\Models\WhatsAppCampaignRecipientExecution;
use App\Models\WhatsAppCampaignUsageReservation;
use App\Services\Campaigns\NodeWhatsAppCampaignTransport;
use App\Services\Campaigns\WhatsAppCampaignRetryPolicy;
use Tests\TestCase;

final class WhatsAppCampaignExecutionSmokeTest extends TestCase
{
    public function test_part_four_transport_uses_the_internal_node_client(): void
    {
        $this->assertInstanceOf(NodeWhatsAppCampaignTransport::class, app(WhatsAppCampaignTransport::class));
    }

    public function test_recipient_cannot_be_mass_assigned_sent_or_attempted(): void
    {
        $row = new WhatsAppCampaignRecipientExecution;
        $row->fill(['status' => WhatsAppCampaignRecipientExecutionStatus::Sent, 'attempt_count' => 99, 'session_id' => 5]);
        $this->assertNull($row->status);
        $this->assertNull($row->attempt_count);
        $this->assertNull($row->session_id);
    }

    public function test_usage_reservation_cannot_be_mass_consumed(): void
    {
        $r = new WhatsAppCampaignUsageReservation;
        $r->fill(['reserved_units' => 10, 'consumed_units' => 10, 'released_units' => 10, 'status' => 'consumed']);
        $this->assertNull($r->consumed_units);
        $this->assertNull($r->released_units);
        $this->assertNull($r->status);
    }

    public function test_retry_policy_is_bounded_and_permanent_failures_do_not_retry(): void
    {
        $policy = app(WhatsAppCampaignRetryPolicy::class);
        $this->assertFalse($policy->decide(WhatsAppCampaignFailureClass::Permanent, 'invalid_phone', 1, 3)->retry);
        $this->assertTrue($policy->decide(WhatsAppCampaignFailureClass::Transient, 'queue_interruption', 1, 3)->retry);
        $this->assertFalse($policy->decide(WhatsAppCampaignFailureClass::Transient, 'queue_interruption', 3, 3)->retry);
    }

    public function test_execution_jobs_serialize_without_models_or_transport_payloads(): void
    {
        foreach ([new StartWhatsAppCampaignExecution(1), new ContinueWhatsAppCampaignExecution(1), new ProcessWhatsAppCampaignRecipient(1)] as $job) {
            $this->assertEquals($job, unserialize(serialize($job)));
        }
    }
}
