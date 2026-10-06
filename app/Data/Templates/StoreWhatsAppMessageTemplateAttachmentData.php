<?php

namespace App\Data\Templates;

use Illuminate\Http\UploadedFile;

final readonly class StoreWhatsAppMessageTemplateAttachmentData
{
    public function __construct(public UploadedFile $file, public int $expectedVersion = 1) {}
}
