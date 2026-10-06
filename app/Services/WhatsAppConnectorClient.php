<?php

namespace App\Services;

use App\Contracts\Messaging\MessagingConnector;
use App\Data\Messaging\ConnectorSessionResult;
use App\Data\Messaging\MessageData;
use App\Data\Messaging\QrCodeResult;
use App\Data\Messaging\SendMessageResult;
use App\Models\WhatsAppSession;
use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class WhatsAppConnectorClient implements MessagingConnector
{
    public function __construct(private InternalRequestSigner $signer) {}

    public function createSession(array $data): ConnectorSessionResult
    {
        return $this->request('POST', '/internal/sessions', $data);
    }

    public function initializeSession(string $sessionReference): ConnectorSessionResult
    {
        return $this->request('POST', "/internal/sessions/{$sessionReference}/initialize");
    }

    public function getQrCode(string $sessionReference): QrCodeResult
    {
        $result = $this->request('GET', "/internal/sessions/{$sessionReference}/qr");

        return new QrCodeResult((string) ($result->metadata['qr'] ?? ''), new DateTimeImmutable((string) ($result->metadata['expires_at'] ?? '+30 seconds')));
    }

    public function getStatus(string $sessionReference): ConnectorSessionResult
    {
        return $this->request('GET', "/internal/sessions/{$sessionReference}");
    }

    public function restart(string $sessionReference): ConnectorSessionResult
    {
        $session = WhatsAppSession::where('uuid', $sessionReference)->with('tenant:id,uuid')->firstOrFail();

        return $this->request('POST', "/internal/sessions/{$sessionReference}/reconnect", ['storage_key' => $session->storage_key, 'tenant_uuid' => $session->tenant->uuid]);
    }

    public function logout(string $sessionReference): ConnectorSessionResult
    {
        return $this->request('POST', "/internal/sessions/{$sessionReference}/disconnect");
    }

    public function delete(string $sessionReference): void
    {
        $session = WhatsAppSession::withTrashed()->where('uuid', $sessionReference)->firstOrFail();
        $this->request('DELETE', "/internal/sessions/{$sessionReference}", ['storage_key' => $session->storage_key]);
    }

    public function send(MessageData $message): SendMessageResult
    {
        try {
            $result = $this->rawRequest('POST', '/internal/messages/send', [
                'message_uuid' => $message->messageReference, 'session_uuid' => $message->sessionReference,
                'storage_key' => $message->storageKey, 'request_id' => $message->requestId, 'recipient' => $message->recipient,
                'type' => $message->type, 'body' => $message->body, 'media' => $message->media, 'expires_at' => $message->expiresAt,
            ]);
        } catch (RequestException $exception) {
            $code = (string) ($exception->response?->json('error') ?? 'connector_rejected');

            return new SendMessageResult(false, null, $code, in_array($code, ['session_not_ready', 'request_in_progress'], true));
        }

        return new SendMessageResult((bool) ($result['accepted'] ?? false), $result['whatsapp_message_id'] ?? null, $result['error_code'] ?? null, (bool) ($result['retryable'] ?? false));
    }

    public function reconcile(string $requestId): SendMessageResult
    {
        try {
            $result = $this->rawRequest('GET', "/internal/messages/requests/{$requestId}");

            return new SendMessageResult((bool) ($result['accepted'] ?? false), $result['whatsapp_message_id'] ?? null);
        } catch (RequestException) {
            return new SendMessageResult(false, null, 'reconciliation_unavailable');
        }
    }

    private function request(string $method, string $path, array $data = []): ConnectorSessionResult
    {
        $json = $this->rawRequest($method, $path, $data);
        if (! isset($json['reference'], $json['status'])) {
            throw new RuntimeException('The WhatsApp connector returned an invalid response.');
        }

        return new ConnectorSessionResult((string) $json['reference'], (string) $json['status'], is_array($json['metadata'] ?? null) ? $json['metadata'] : []);
    }

    private function rawRequest(string $method, string $path, array $data = []): array
    {
        $body = $data === [] ? '' : (string) json_encode($data, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $nonce = Str::random(32);
        $secret = (string) config('whatsapp.hmac_secret');
        if (strlen($secret) < 32) {
            throw new RuntimeException('WhatsApp connector authentication is not configured.');
        }
        try {
            $response = Http::baseUrl((string) config('whatsapp.connector_url'))
                ->timeout(config('whatsapp.request_timeout_seconds'))
                ->acceptJson()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Internal-Timestamp' => $timestamp,
                    'X-Internal-Nonce' => $nonce,
                    'X-Internal-Signature' => $this->signer->sign($method, $path, $timestamp, $nonce, $body, $secret),
                    'Idempotency-Key' => (string) Str::uuid(),
                ])
                ->send($method, $path, ['body' => $body])
                ->throw();
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The WhatsApp connector is unavailable.', previous: $exception);
        }
        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('The WhatsApp connector returned an invalid response.');
        }

        return $json;
    }
}
