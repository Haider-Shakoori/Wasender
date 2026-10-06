<?php

namespace Database\Factories;

use App\Enums\WhatsAppMessageTemplateVersionStatus;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppMessageTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

final class WhatsAppMessageTemplateVersionFactory extends Factory
{
    protected $model = WhatsAppMessageTemplateVersion::class;

    public function definition(): array
    {
        return ['whatsapp_message_template_id' => WhatsAppMessageTemplate::factory(), 'version_number' => 1, 'status' => WhatsAppMessageTemplateVersionStatus::Draft, 'body' => 'Hello from the template', 'caption' => null, 'content_configuration' => [], 'variable_context' => 'contact', 'variable_configuration' => [], 'parser_version' => 1, 'renderer_version' => 1];
    }

    public function published(): self
    {
        return $this->state(['status' => WhatsAppMessageTemplateVersionStatus::Published, 'published_at' => now()]);
    }
}
