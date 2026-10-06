<?php

namespace Database\Factories;

use App\Models\WhatsAppCampaignRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

final class WhatsAppCampaignRecipientFactory extends Factory
{
    protected $model = WhatsAppCampaignRecipient::class;

    public function definition(): array
    {
        $phone = '+1555010'.$this->faker->unique()->numerify('###');

        return ['uuid' => $this->faker->uuid(), 'contact_uuid' => $this->faker->uuid(), 'phone_normalized' => $phone, 'whatsapp_address' => ltrim($phone, '+').'@c.us', 'consent_status' => 'granted', 'recipient_status' => 'prepared', 'source_type' => 'manual_contacts', 'deduplication_key' => hash('sha256', $phone), 'campaign_version' => 1, 'campaign_payload_hash' => hash('sha256', 'payload'), 'prepared_at' => now()];
    }

    public function eligibleRecipient(): static
    {
        return $this->state(['recipient_status' => 'prepared', 'consent_status' => 'granted']);
    }
}
