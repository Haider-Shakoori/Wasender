<?php

namespace App\Enums;

enum WhatsAppMessageTemplateStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
