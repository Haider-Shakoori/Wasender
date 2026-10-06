<?php

namespace Database\Factories;

use App\Enums\WhatsAppCampaignPreparationStatus;
use App\Models\WhatsAppCampaignPreparation;
use Illuminate\Database\Eloquent\Factories\Factory;

final class WhatsAppCampaignPreparationFactory extends Factory
{
    protected $model = WhatsAppCampaignPreparation::class;

    public function definition(): array
    {
        return ['uuid' => $this->faker->uuid(), 'status' => WhatsAppCampaignPreparationStatus::Pending, 'campaign_version' => 1, 'campaign_payload_hash' => hash('sha256', 'payload'), 'audience_type' => 'all_eligible_contacts', 'audience_definition_hash' => hash('sha256', 'audience'), 'idempotency_key' => $this->faker->uuid()];
    }

    public function running(): static
    {
        return $this->state(['status' => WhatsAppCampaignPreparationStatus::Running, 'started_at' => now()]);
    }

    public function completed(): static
    {
        return $this->state(['status' => WhatsAppCampaignPreparationStatus::Completed, 'started_at' => now(), 'completed_at' => now(), 'progress_percentage' => 100]);
    }

    public function failed(): static
    {
        return $this->state(['status' => WhatsAppCampaignPreparationStatus::Failed, 'failed_at' => now(), 'failure_code' => 'internal_error']);
    }

    public function stale(): static
    {
        return $this->state(['status' => WhatsAppCampaignPreparationStatus::Stale]);
    }
}
