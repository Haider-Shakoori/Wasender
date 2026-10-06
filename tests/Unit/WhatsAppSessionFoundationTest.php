<?php

namespace Tests\Unit;

use App\Enums\WhatsAppSessionStatus;
use App\Models\WhatsAppSession;
use App\Services\InternalRequestSigner;
use PHPUnit\Framework\TestCase;

final class WhatsAppSessionFoundationTest extends TestCase
{
    public function test_internal_request_signature_is_canonical_and_body_sensitive(): void
    {
        $signer = new InternalRequestSigner;
        $first = $signer->sign('post', '/internal/sessions', '100', 'nonce', '{"a":1}', 'secret');

        $this->assertSame($first, $signer->sign('POST', '/internal/sessions', '100', 'nonce', '{"a":1}', 'secret'));
        $this->assertNotSame($first, $signer->sign('POST', '/internal/sessions', '100', 'nonce', '{"a":2}', 'secret'));
    }

    public function test_session_uses_explicit_table_and_transitional_states_are_known(): void
    {
        $this->assertSame('whatsapp_sessions', (new WhatsAppSession)->getTable());
        $this->assertTrue(WhatsAppSessionStatus::QrPending->isTransitional());
        $this->assertFalse(WhatsAppSessionStatus::Ready->isTransitional());
        $this->assertSame('Connected', WhatsAppSessionStatus::Ready->label());
    }
}
