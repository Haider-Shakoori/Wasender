<?php

namespace App\Presenters;

final class WhatsAppInboxFailurePresenter
{
    public function present(?string $code, bool $retryable): array
    {
        return ['title' => str($code ?: 'delivery_failed')->replace('_', ' ')->headline()->toString(), 'description' => $retryable ? 'Delivery failed and the canonical message record permits retry.' : 'Delivery failed and requires review before another attempt.', 'retryable' => $retryable];
    }
}
