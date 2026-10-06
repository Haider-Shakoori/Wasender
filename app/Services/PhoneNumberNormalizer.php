<?php

namespace App\Services;

use App\Data\Contacts\NormalizedPhoneNumber;
use Illuminate\Validation\ValidationException;

final class PhoneNumberNormalizer
{
    public function normalize(string $input): NormalizedPhoneNumber
    {
        $value = trim($input);
        if (str_contains(strtolower($value), '@')) {
            throw ValidationException::withMessages(['phone' => 'WhatsApp identifiers are not accepted.']);
        }
        $digits = preg_replace('/[\s\-().]/', '', $value);
        if (str_starts_with((string) $digits, '+')) {
            $digits = substr((string) $digits, 1);
        }
        if (! is_string($digits) || ! preg_match('/^[1-9][0-9]{7,14}$/', $digits)) {
            throw ValidationException::withMessages(['phone' => 'Use an international number with country code (8–15 digits).']);
        }

        return new NormalizedPhoneNumber($input, '+'.$digits, null, $digits.'@c.us');
    }
}
