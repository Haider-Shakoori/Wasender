<?php

namespace Tests\Unit;

use App\Data\Campaigns\CampaignPayload;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\WhatsAppCampaign;
use Tests\TestCase;

final class WhatsAppCampaignSmokeTest extends TestCase
{
    public function test_payload_canonicalization_is_deterministic(): void
    {
        $first = new CampaignPayload(['sessions' => ['b', 'a'], 'audience' => ['type' => 'groups', 'ids' => ['2', '1']]]);
        $second = new CampaignPayload(['audience' => ['ids' => ['1', '2'], 'type' => 'groups'], 'sessions' => ['a', 'b']]);

        $this->assertSame($first->canonical(), $second->canonical());
    }

    public function test_lifecycle_editable_states_are_centralized(): void
    {
        $this->assertTrue(WhatsAppCampaignStatus::Draft->editable());
        $this->assertTrue(WhatsAppCampaignStatus::NeedsAttention->editable());
        $this->assertTrue(WhatsAppCampaignStatus::Ready->editable());
        $this->assertFalse(WhatsAppCampaignStatus::Scheduled->editable());
        $this->assertTrue(WhatsAppCampaignStatus::Archived->terminal());
    }

    public function test_protected_campaign_fields_are_not_mass_assignable(): void
    {
        $campaign = new WhatsAppCampaign;
        $campaign->fill(['name' => 'Allowed', 'tenant_id' => 99, 'status' => 'running', 'sent_recipient_count' => 42]);

        $this->assertSame('Allowed', $campaign->name);
        $this->assertNull($campaign->tenant_id);
        $this->assertNull($campaign->status);
        $this->assertNull($campaign->sent_recipient_count);
    }
}
