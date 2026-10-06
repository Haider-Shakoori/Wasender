<?php

namespace App\Enums;

enum WhatsAppSessionStatus: string
{
    case Creating = 'creating';
    case Initializing = 'initializing';
    case QrPending = 'qr_pending';
    case Authenticating = 'authenticating';
    case Authenticated = 'authenticated';
    case Ready = 'ready';
    case Reconnecting = 'reconnecting';
    case Disconnected = 'disconnected';
    case Failed = 'failed';
    case Deleting = 'deleting';
    case Deleted = 'deleted';

    public function isTransitional(): bool
    {
        return in_array($this, [self::Creating, self::Initializing, self::QrPending, self::Authenticating, self::Authenticated, self::Reconnecting, self::Deleting], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::QrPending => 'Scan QR code',
            self::Ready => 'Connected',
            default => str($this->value)->replace('_', ' ')->headline()->toString(),
        };
    }
}
