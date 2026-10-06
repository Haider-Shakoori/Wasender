<?php

namespace App\Services\Integrations;

use Illuminate\Validation\ValidationException;

final class IntegrationUrlGuard
{
    public function validate(string $url): string
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || blank($parts['host'] ?? null)) {
            throw ValidationException::withMessages(['url' => 'Only HTTPS webhook URLs are allowed.']);
        }
        $host = strtolower($parts['host']);
        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            $this->reject();
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            throw ValidationException::withMessages(['url' => 'Webhook host could not be resolved.']);
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $this->reject();
            }
        }

        return $url;
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['url' => 'Private, loopback, and local webhook targets are not allowed.']);
    }
}
