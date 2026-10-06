<?php

namespace Database\Factories;

use App\Models\WhatsAppCampaignExecution;
use Illuminate\Database\Eloquent\Factories\Factory;

final class WhatsAppCampaignExecutionFactory extends Factory
{
    protected $model = WhatsAppCampaignExecution::class;

    public function definition(): array
    {
        return ['uuid' => $this->faker->uuid(), 'status' => 'pending', 'campaign_version' => 1, 'campaign_payload_hash' => hash('sha256', 'payload'), 'execution_config_hash' => hash('sha256', 'config'), 'idempotency_key' => $this->faker->uuid(), 'launch_type' => 'manual'];
    }

    public function queued(): static
    {
        return $this->state(['status' => 'queued', 'queued_at' => now()]);
    }

    public function running(): static
    {
        return $this->state(['status' => 'running', 'started_at' => now()]);
    }

    public function paused(): static
    {
        return $this->state(['status' => 'paused', 'paused_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(['status' => 'failed', 'failed_at' => now()]);
    }
}
