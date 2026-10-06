<?php

namespace App\Presenters;

use App\Enums\WhatsAppInboxMessageStatus;

final class WhatsAppInboxMessageStatusPresenter
{
    public function present(WhatsAppInboxMessageStatus $status): array
    {
        return ['label' => str($status->value)->headline()->toString(), 'severity' => $status === WhatsAppInboxMessageStatus::Failed ? 'danger' : ($status === WhatsAppInboxMessageStatus::Read ? 'success' : 'neutral')];
    }
}
