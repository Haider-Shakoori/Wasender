<?php

namespace App\Services;

final class WhatsAppRecipientNormalizer
{
    public function normalize(string $recipient): string
    {
        return ltrim((new PhoneNumberNormalizer)->normalize($recipient)->e164, '+');
    }
}
