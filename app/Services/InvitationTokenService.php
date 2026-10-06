<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Tenancy\InvitationToken;

final class InvitationTokenService
{
    public function generate(): InvitationToken
    {
        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return new InvitationToken($plain, $this->hash($plain));
    }

    public function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function matches(string $plainToken, string $storedHash): bool
    {
        return hash_equals($storedHash, $this->hash($plainToken));
    }
}
