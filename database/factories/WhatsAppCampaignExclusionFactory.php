<?php

namespace Database\Factories;

use App\Models\WhatsAppCampaignExclusion;
use Illuminate\Database\Eloquent\Factories\Factory;

final class WhatsAppCampaignExclusionFactory extends Factory
{
    protected $model = WhatsAppCampaignExclusion::class;

    public function definition(): array
    {
        return ['uuid' => $this->faker->uuid(), 'contact_uuid' => $this->faker->uuid(), 'reason_code' => 'opted_out', 'reason_codes' => ['opted_out'], 'source_type' => 'groups', 'safe_summary' => 'Opted out', 'created_at' => now()];
    }

    public function optedOutExclusion(): static
    {
        return $this->state(['reason_code' => 'opted_out']);
    }

    public function duplicateExclusion(): static
    {
        return $this->state(['reason_code' => 'duplicate_phone']);
    }

    public function invalidPhoneExclusion(): static
    {
        return $this->state(['reason_code' => 'invalid_phone']);
    }
}
