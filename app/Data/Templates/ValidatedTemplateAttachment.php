<?php

namespace App\Data\Templates;

final readonly class ValidatedTemplateAttachment
{
    public function __construct(public string $mimeType, public string $mediaCategory, public int $sizeBytes, public string $checksum, public string $extension, public string $safeName) {}
}
