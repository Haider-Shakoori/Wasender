<?php

namespace Tests\Feature;

use App\Services\InternalRequestSigner;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class WhatsAppCampaignTransportSmokeTest extends TestCase
{
    public function test_unsigned_campaign_callback_is_rejected(): void
    {
        $this->postJson('/internal/whatsapp/campaign-events', [])->assertUnauthorized();
    }

    public function test_campaign_attachment_requires_internal_authentication(): void
    {
        $this->get('/internal/whatsapp/campaign-attachments/00000000-0000-4000-8000-000000000000')->assertUnauthorized();
    }

    public function test_no_laravel_route_exposes_node_campaign_dispatch_to_tenants(): void
    {
        $this->assertFalse(collect(Route::getRoutes())->contains(fn ($route) => $route->uri() === 'internal/v1/campaign-messages/send'));
        $this->postJson('/internal/v1/campaign-messages/send', ['recipient' => '+15551234567'])->assertNotFound();
    }

    public function test_extended_hmac_binds_request_and_idempotency_ids(): void
    {
        $signer = app(InternalRequestSigner::class);
        $base = $signer->sign('POST', '/internal/test', '100', 'nonce', '{}', 'secret');
        $extended = $signer->sign('POST', '/internal/test', '100', 'nonce', '{}', 'secret', 'request-id', 'idempotency-id');
        $this->assertNotSame($base, $extended);
        $this->assertSame($extended, $signer->sign('post', '/internal/test', '100', 'nonce', '{}', 'secret', 'request-id', 'idempotency-id'));
    }
}
