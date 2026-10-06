<?php

namespace App\Contracts;

use App\Models\WhatsAppMessageTemplateVersion;

interface WhatsAppMessageTemplateUsageReference
{
    public function countReferences(WhatsAppMessageTemplateVersion $version): int;
}
