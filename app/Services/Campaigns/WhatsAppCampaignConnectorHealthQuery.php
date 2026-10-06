<?php

namespace App\Services\Campaigns;

use App\Models\WhatsAppCampaignConnectorEvent;
use App\Models\WhatsAppCampaignDispatchAttempt;
use App\Services\InternalRequestSigner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class WhatsAppCampaignConnectorHealthQuery
{
    public function __construct(private InternalRequestSigner $signer) {}

    public function get(): array
    {
        $remote = ['status' => 'unavailable', 'campaign_transport' => []];
        try {
            $path = '/internal/health';
            $timestamp = (string) now()->timestamp;
            $nonce = Str::random(40);
            $secret = (string) config('whatsapp.hmac_secret');
            $response = Http::baseUrl((string) config('whatsapp.connector_url'))->connectTimeout(2)->timeout(4)->acceptJson()->withHeaders([
                'X-Internal-Timestamp' => $timestamp,
                'X-Internal-Nonce' => $nonce,
                'X-Internal-Signature' => $this->signer->sign('GET', $path, $timestamp, $nonce, '', $secret),
            ])->get($path);
            if ($response->successful() && is_array($response->json())) {
                $remote = $response->json();
            }
        } catch (Throwable) {
            // The operations page remains available when the connector is offline.
        }

        return [
            'remote' => $remote,
            'active_dispatches' => WhatsAppCampaignDispatchAttempt::whereIn('status', ['processing', 'transport_pending'])->count(),
            'unknown_attempts' => WhatsAppCampaignDispatchAttempt::where('status', 'unknown')->count(),
            'callback_backlog' => WhatsAppCampaignConnectorEvent::whereIn('status', ['received', 'processing'])->count(),
            'oldest_callback' => WhatsAppCampaignConnectorEvent::whereIn('status', ['received', 'processing'])->min('created_at'),
            'recent_failures' => WhatsAppCampaignDispatchAttempt::whereNotNull('failure_code')->where('updated_at', '>=', now()->subDay())->selectRaw('failure_code,count(*) total')->groupBy('failure_code')->orderByDesc('total')->limit(10)->pluck('total', 'failure_code'),
            'last_reconciliation' => WhatsAppCampaignDispatchAttempt::max('last_reconciled_at'),
        ];
    }
}
