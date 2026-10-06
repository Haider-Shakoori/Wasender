<?php

namespace App\Data\Templates;

final readonly class RenderedWhatsAppTemplateAttachment
{
    public function __construct(public string $attachmentUuid, public string $mimeType, public int $sizeBytes, public string $checksumSha256, public string $originalName, public string $mediaCategory) {}
}
