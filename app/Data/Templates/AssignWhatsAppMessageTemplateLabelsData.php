<?php

namespace App\Data\Templates;

final readonly class AssignWhatsAppMessageTemplateLabelsData
{
    public function __construct(public array $labelUuids) {}

    public static function from(array $data): self
    {
        return new self($data['label_uuids'] ?? []);
    }
}
