<?php

namespace Database\Factories;

use App\Models\WhatsAppCampaignRecipientExecution;
use Illuminate\Database\Eloquent\Factories\Factory;

final class WhatsAppCampaignRecipientExecutionFactory extends Factory
{
    protected $model = WhatsAppCampaignRecipientExecution::class;

    public function definition(): array
    {
        return ['uuid' => $this->faker->uuid(), 'status' => 'pending', 'attempt_count' => 0, 'max_attempts' => 2, 'idempotency_key' => $this->faker->uuid(), 'lock_version' => 1];
    }

    public function transportPending(): static
    {
        return $this->state(['status' => 'transport_pending']);
    }

    public function retryScheduled(): static
    {
        return $this->state(['status' => 'retry_scheduled', 'next_attempt_at' => now()->addMinute()]);
    }

    public function permanentFailure(): static
    {
        return $this->state(['status' => 'failed', 'failure_code' => 'invalid_phone']);
    }
}
