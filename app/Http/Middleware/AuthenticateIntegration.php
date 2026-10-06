<?php

namespace App\Http\Middleware;

use App\Contracts\TenantContext;
use App\Models\Integration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateIntegration
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(strlen($request->getContent()) > config('integrations.max_payload_bytes'), 413);
        $integration = Integration::where('uuid', (string) $request->route('integration'))->with('tenant')->firstOrFail();
        abort_unless($integration->is_enabled && $integration->status === 'active', 403, 'Integration is disabled.');
        $credentials = $integration->credentials_encrypted ?? [];
        $token = $request->bearerToken();
        $timestamp = (string) $request->header('X-Webhook-Timestamp');
        $signature = (string) $request->header('X-Webhook-Signature');
        $nonce = (string) $request->header('X-Webhook-Id');
        $tokenValid = is_string($token) && isset($credentials['token_hash']) && hash_equals($credentials['token_hash'], hash('sha256', $token));
        $signatureValid = ctype_digit($timestamp) && abs(now()->timestamp - (int) $timestamp) <= config('security.integration_signature_tolerance_seconds') && strlen($nonce) >= 16 && isset($credentials['webhook_secret']) && hash_equals(hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $credentials['webhook_secret']), $signature) && Cache::add('integration:nonce:'.hash('sha256', $integration->uuid.'|'.$nonce), true, config('security.integration_signature_tolerance_seconds'));
        $wooSignature = (string) $request->header('X-WC-Webhook-Signature');
        $wooValid = $integration->provider->value === 'woocommerce'
            && $request->routeIs('api.integrations.events')
            && $wooSignature !== ''
            && isset($credentials['webhook_secret'])
            && hash_equals(base64_encode(hash_hmac('sha256', $request->getContent(), $credentials['webhook_secret'], true)), $wooSignature);
        abort_unless($tokenValid || $signatureValid || $wooValid, 401);
        app(TenantContext::class)->set($integration->tenant);
        $request->attributes->set('integration_model', $integration);
        $integration->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }
}
