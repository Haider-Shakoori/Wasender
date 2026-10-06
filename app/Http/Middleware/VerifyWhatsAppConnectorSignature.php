<?php

namespace App\Http\Middleware;

use App\Services\InternalRequestSigner;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class VerifyWhatsAppConnectorSignature
{
    public function __construct(private InternalRequestSigner $signer) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if(strlen($request->getContent()) > 300000, 413);
        $timestamp = (string) $request->header('X-Internal-Timestamp');
        $nonce = (string) $request->header('X-Internal-Nonce');
        $signature = (string) $request->header('X-Internal-Signature');
        $requestId = (string) $request->header('X-Internal-Request-Id');
        $idempotencyKey = (string) $request->header('X-Internal-Idempotency-Key');
        $contentHash = (string) $request->header('X-Internal-Content-SHA256');
        $source = (string) $request->header('X-Internal-Source');
        $secret = (string) config('whatsapp.hmac_secret');
        $validTime = ctype_digit($timestamp) && abs(now()->timestamp - (int) $timestamp) <= config('whatsapp.signature_tolerance_seconds');
        $expected = $secret !== '' ? $this->signer->sign($request->method(), '/'.$request->path(), $timestamp, $nonce, $request->getContent(), $secret, $requestId, $idempotencyKey) : '';
        $validHash = preg_match('/\A[0-9a-f]{64}\z/', $contentHash) === 1 && hash_equals(hash('sha256', $request->getContent()), $contentHash);
        $nonceKey = 'wa:callback:nonce:'.hash('sha256', $nonce);
        abort_unless($source === 'whatsapp-connector' && $validTime && $validHash && strlen($nonce) >= 16 && hash_equals($expected, $signature) && Cache::add($nonceKey, true, config('whatsapp.signature_tolerance_seconds')), 401);

        return $next($request);
    }
}
