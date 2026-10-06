<?php

namespace App\Services\Templates;

use App\Enums\WhatsAppMessageTemplateStatus;
use App\Models\WhatsAppMessageTemplate;
use Illuminate\Validation\ValidationException;

final class WhatsAppMessageTemplateLifecycleGuard
{
    public function editable(WhatsAppMessageTemplate $template): void
    {
        if ($template->status === WhatsAppMessageTemplateStatus::Archived) {
            throw ValidationException::withMessages(['status' => 'Archived templates are read-only.']);
        }
    }

    public function expected(WhatsAppMessageTemplate $template, int $expected): void
    {
        if ($template->lock_version !== $expected) {
            throw ValidationException::withMessages(['expected_version' => 'This template changed in another request. Refresh and try again.']);
        }
    }

    public function typeChangeAllowed(WhatsAppMessageTemplate $template, string $type): void
    {
        if ($template->current_published_version_id && $template->type->value !== $type) {
            throw ValidationException::withMessages(['type' => 'Template type cannot change after first publication.']);
        }
    }
}
