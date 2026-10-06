<?php

namespace App\Data\Contacts;

final readonly class NormalizedPhoneNumber
{
    public function __construct(public string $input, public string $e164, public ?string $countryCode, public string $whatsappAddress) {}
}
