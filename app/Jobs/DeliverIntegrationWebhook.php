<?php

namespace App\Jobs;

use App\Models\IntegrationWebhookDelivery;
use App\Services\Integrations\IntegrationUrlGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class DeliverIntegrationWebhook implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 3;

    public int $timeout = 20;

    public function __construct(public int $deliveryId, public string $url, public array $payload) {}

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(IntegrationUrlGuard $urls): void
    {
        $delivery = IntegrationWebhookDelivery::with('integration')->find($this->deliveryId);
        if (! $delivery || $delivery->status === 'completed') {
            return;
        }
        $integration = $delivery->integration;
        $secret = data_get($integration->credentials_encrypted, 'webhook_secret');
        if (! $secret) {
            $this->failDelivery($delivery, 'secret_missing');

            return;
        }
        $urls->validate($this->url);
        $timestamp = (string) now()->timestamp;
        $body = ['event_type' => $delivery->event_type, 'event_id' => $delivery->event_id, 'data' => $this->payload];
        $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $timestamp.'.'.$encoded, $secret);
        $delivery->increment('attempt_count');
        $delivery->update(['status' => 'sending', 'sent_at' => now()]);
        try {
            $response = Http::timeout(config('integrations.http_timeout_seconds'))->withHeaders(['X-Webhook-Id' => $delivery->uuid, 'X-Webhook-Timestamp' => $timestamp, 'X-Webhook-Signature' => $signature])->withBody($encoded, 'application/json')->post($this->url);
            if (! $response->successful()) {
                throw new RuntimeException('remote_http_error');
            }
            $delivery->update(['status' => 'completed', 'response_status' => $response->status(), 'completed_at' => now(), 'failure_code' => null]);
            $integration->update(['last_success_at' => now(), 'last_failure_code' => null]);
        } catch (\Throwable $e) {
            $final = $delivery->attempt_count >= config('integrations.max_attempts');
            $delivery->update(['status' => $final ? 'failed' : 'retrying', 'response_status' => isset($response) ? $response->status() : null, 'failure_code' => 'delivery_failed', 'next_retry_at' => $final ? null : now()->addSeconds($this->backoff()[min($delivery->attempt_count - 1, 2)])]);
            $integration->update(['last_failure_at' => now(), 'last_failure_code' => 'delivery_failed']);
            if (! $final) {
                throw $e;
            }
        }
    }

    private function failDelivery(IntegrationWebhookDelivery $delivery, string $code): void
    {
        $delivery->update(['status' => 'failed', 'failure_code' => $code, 'completed_at' => now()]);
    }
}
