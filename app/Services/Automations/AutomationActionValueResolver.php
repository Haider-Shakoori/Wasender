<?php

namespace App\Services\Automations;

use App\Models\Contact;
use Illuminate\Validation\ValidationException;

final class AutomationActionValueResolver
{
    private const CONTACT = ['first_name', 'last_name', 'company', 'preferred_language', 'timezone', 'phone', 'email', 'full_name', 'display_name'];

    public function resolve(array $mapping, Contact $contact, array $context): mixed
    {
        $source = $mapping['source'] ?? null;
        $value = match ($source) {
            'constant' => $mapping['value'] ?? null,'context' => $this->context($mapping['key'] ?? '', $context['values'] ?? []),'trigger' => $this->context($mapping['key'] ?? '', $context['trigger'] ?? []),'contact' => $this->contact($mapping['key'] ?? '', $contact),default => throw ValidationException::withMessages(['action' => 'Invalid value source.'])
        };
        if (! is_scalar($value) && $value !== null) {
            throw ValidationException::withMessages(['action' => 'Action values must be scalar.']);
        }if (is_string($value) && mb_strlen($value) > 500) {
            throw ValidationException::withMessages(['action' => 'Action value is too long.']);
        }

        return $value;
    }

    private function context(string $key, array $values): mixed
    {
        if (! preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $key) || ! array_key_exists($key, $values)) {
            throw ValidationException::withMessages(['action' => 'Required action value is missing.']);
        }

        return $values[$key];
    }

    private function contact(string $key, Contact $contact): mixed
    {
        if (! in_array($key, self::CONTACT, true)) {
            throw ValidationException::withMessages(['action' => 'Contact field is not allowed.']);
        }

        return match ($key) {
            'phone' => $contact->phone_normalized,'full_name' => trim("{$contact->first_name} {$contact->last_name}"),'display_name' => $contact->first_name ?: $contact->phone_normalized,default => $contact->{$key}
        };
    }
}
