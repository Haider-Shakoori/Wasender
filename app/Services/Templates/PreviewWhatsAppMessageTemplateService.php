<?php

namespace App\Services\Templates;

use App\Data\Templates\RenderedWhatsAppTemplatePayload;
use App\Data\Templates\WhatsAppTemplateRenderContext;
use App\Models\WhatsAppMessageTemplateVersion;
use Carbon\CarbonImmutable;

final class PreviewWhatsAppMessageTemplateService
{
    public function __construct(private WhatsAppTemplateVariableRegistry $registry, private WhatsAppMessageTemplateRenderer $renderer) {}

    public function preview(WhatsAppMessageTemplateVersion $version, array $overrides = [], string $timezone = 'UTC', ?CarbonImmutable $now = null): RenderedWhatsAppTemplatePayload
    {
        $values = [];
        foreach ($this->registry->forContext($version->variable_context) as $definition) {
            $values[$definition->key] = $definition->previewValue;
        }

        return $this->renderer->render($version, new WhatsAppTemplateRenderContext($version->variable_context, array_replace($values, $overrides), $timezone, $now));
    }
}
