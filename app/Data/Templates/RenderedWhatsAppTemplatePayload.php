<?php

namespace App\Data\Templates;

use App\Enums\WhatsAppMessageTemplateType;
use Carbon\CarbonImmutable;

final readonly class RenderedWhatsAppTemplatePayload
{
    public function __construct(public string $templateUuid, public string $templateVersionUuid, public int $templateVersionNumber, public WhatsAppMessageTemplateType $type, public ?string $body, public ?string $caption, public array $resolvedVariables, public array $warnings, public string $templateContentHash, public string $renderHash, public CarbonImmutable $renderedAt, public ?RenderedWhatsAppTemplateAttachment $attachment = null) {}
}
