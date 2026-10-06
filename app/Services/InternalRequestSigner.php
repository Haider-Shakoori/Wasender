<?php

namespace App\Services;

final class InternalRequestSigner
{
    public function sign(string $method, string $path, string $timestamp, string $nonce, string $body, string $secret, string $requestId = '', string $idempotencyKey = ''): string
    {
        $canonical = strtoupper($method)."\n".$path."\n".$timestamp."\n".$nonce."\n";
        if ($requestId !== '' || $idempotencyKey !== '') {
            $canonical .= $requestId."\n".$idempotencyKey."\n";
        }
        $canonical .= hash('sha256', $body);

        return hash_hmac('sha256', $canonical, $secret);
    }
}
