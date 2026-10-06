<?php

namespace Database\Factories;

use App\Enums\WhatsAppMessageTemplateStatus;
use App\Enums\WhatsAppMessageTemplateType;
use App\Models\Tenant;
use App\Models\WhatsAppMessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

final class WhatsAppMessageTemplateFactory extends Factory
{
    protected $model = WhatsAppMessageTemplate::class;

    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'name' => fake()->unique()->words(3, true), 'description' => null, 'type' => WhatsAppMessageTemplateType::Text, 'status' => WhatsAppMessageTemplateStatus::Draft, 'lock_version' => 1];
    }

    public function archived(): self
    {
        return $this->state(['status' => WhatsAppMessageTemplateStatus::Archived, 'archived_at' => now()]);
    }
}
