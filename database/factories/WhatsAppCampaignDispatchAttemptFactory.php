<?php

namespace Database\Factories;

use App\Models\WhatsAppCampaignDispatchAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

final class WhatsAppCampaignDispatchAttemptFactory extends Factory
{
    protected $model = WhatsAppCampaignDispatchAttempt::class;

    public function definition(): array
    {
        return ['uuid' => $this->faker->uuid(), 'attempt_number' => 1, 'status' => 'created', 'idempotency_key' => hash('sha256', $this->faker->uuid()), 'transport_request_hash' => hash('sha256', 'request')];
    }

    public function transportPending(): static
    {
        return $this->state(['status' => 'transport_pending']);
    }

    public function transientFailure(): static
    {
        return $this->state(['status' => 'failed_transient', 'failure_class' => 'transient', 'failure_code' => 'temporary_transport_unavailable']);
    }

    public function permanentFailure(): static
    {
        return $this->state(['status' => 'failed_permanent', 'failure_class' => 'permanent', 'failure_code' => 'invalid_phone']);
    }
}
