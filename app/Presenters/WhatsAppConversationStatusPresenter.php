<?php

namespace App\Presenters;

use App\Enums\WhatsAppConversationStatus;

final class WhatsAppConversationStatusPresenter
{
    public function present(WhatsAppConversationStatus $status): array
    {
        return ['label' => str($status->value)->headline()->toString(), 'severity' => match ($status) {
            WhatsAppConversationStatus::Open => 'success', WhatsAppConversationStatus::Pending => 'warning', WhatsAppConversationStatus::Closed => 'neutral', WhatsAppConversationStatus::Archived => 'muted'
        }];
    }
}
