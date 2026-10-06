<?php

namespace App\Services\Campaigns;

use App\Data\Campaigns\CampaignTransportDispatchResult;
use App\Data\Campaigns\CampaignTransportLookupResult;
use App\Data\Campaigns\CampaignTransportRequest;
use App\Services\InternalRequestSigner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class NodeWhatsAppCampaignClient
{
    public function __construct(private InternalRequestSigner $signer) {}

    public function dispatch(CampaignTransportRequest $request, string $transportRequestHash): CampaignTransportDispatchResult
    {
        try {
            $json = $this->request('POST', '/internal/v1/campaign-messages/send', $request->toArray($transportRequestHash), $request->idempotencyKey, config('whatsapp_campaign_transport.request_timeout_seconds'));
        } catch (ConnectionException) {
            return new CampaignTransportDispatchResult(true, false, null, 'transport_state_unknown', true, 'unknown');
        } catch (RequestException $e) {
            $failure = $e->response?->json('failure');

            return new CampaignTransportDispatchResult(true, false, null, (string) ($failure['code'] ?? $e->response?->json('error') ?? 'transport_rejected'), (bool) ($failure['retryable'] ?? false), 'failed', null, (string) ($failure['class'] ?? 'transport'));
        }

        return new CampaignTransportDispatchResult(
            true,
            (bool) ($json['success'] ?? false),
            $json['transport_reference'] ?? null,
            $json['failure']['code'] ?? null,
            (bool) ($json['failure']['retryable'] ?? false),
            (string) ($json['status'] ?? 'unknown'),
            $json['whatsapp_message_id'] ?? null,
            $json['failure']['class'] ?? null,
        );
    }

    public function lookup(string $attemptUuid, string $tenantUuid, string $idempotencyKey): CampaignTransportLookupResult
    {
        try {
            $json = $this->request('GET', "/internal/v1/campaign-dispatches/{$attemptUuid}", [], $idempotencyKey, config('whatsapp_campaign_transport.lookup_timeout_seconds'), ['X-Campaign-Tenant-Uuid' => $tenantUuid]);
        } catch (RequestException $e) {
            return new CampaignTransportLookupResult($e->response?->status() === 404 ? 'not_found' : 'unknown', failureCode: 'dispatch_lookup_failed', retryable: true);
        } catch (ConnectionException) {
            return new CampaignTransportLookupResult('unknown', failureCode: 'connector_unavailable', retryable: true);
        }

        return new CampaignTransportLookupResult((string) ($json['status'] ?? 'unknown'), $json['transport_reference'] ?? null, $json['whatsapp_message_id'] ?? null, $json['failure']['class'] ?? null, $json['failure']['code'] ?? null, (bool) ($json['failure']['retryable'] ?? false));
    }

    private function request(string $method, string $path, array $data, string $idempotencyKey, int $timeout, array $extraHeaders = []): array
    {
        $body = $data === [] ? '' : json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $nonce = Str::random(40);
        $requestId = (string) Str::uuid();
        $secret = (string) config('whatsapp.hmac_secret');
        if (strlen($secret) < 32) {
            throw new RuntimeException('WhatsApp connector authentication is not configured.');
        }
        $response = Http::baseUrl((string) config('whatsapp.connector_url'))
            ->connectTimeout(config('whatsapp_campaign_transport.connect_timeout_seconds'))
            ->timeout($timeout)
            ->acceptJson()
            ->withHeaders(array_merge([
                'Content-Type' => 'application/json',
                'X-Internal-Timestamp' => $timestamp,
                'X-Internal-Nonce' => $nonce,
                'X-Internal-Request-Id' => $requestId,
                'X-Internal-Idempotency-Key' => $idempotencyKey,
                'X-Internal-Content-SHA256' => hash('sha256', $body),
                'X-Internal-Signature' => $this->signer->sign($method, $path, $timestamp, $nonce, $body, $secret, $requestId, $idempotencyKey),
            ], $extraHeaders))
            ->send($method, $path, ['body' => $body])
            ->throw();
        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('The WhatsApp connector returned an invalid campaign response.');
        }

        return $json;
    }
}
