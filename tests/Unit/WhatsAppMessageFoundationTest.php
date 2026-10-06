<?php

namespace Tests\Unit;

use App\Enums\WhatsAppMessageStatus;
use App\Services\WhatsAppRecipientNormalizer;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class WhatsAppMessageFoundationTest extends TestCase
{
    public function test_recipient_normalization_accepts_international_format(): void
    {
        $this->assertSame('15551234567', (new WhatsAppRecipientNormalizer)->normalize('+1 (555) 123-4567'));
    }

    public function test_recipient_normalization_rejects_groups_and_local_numbers(): void
    {
        $normalizer = new WhatsAppRecipientNormalizer;
        foreach (['12345', '120363@g.us', 'status@broadcast'] as $recipient) {
            try {
                $normalizer->normalize($recipient);
                $this->fail("{$recipient} should be rejected");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_terminal_and_cancellable_states_are_explicit(): void
    {
        $this->assertTrue(WhatsAppMessageStatus::Queued->canCancel());
        $this->assertFalse(WhatsAppMessageStatus::Sending->canCancel());
        $this->assertTrue(WhatsAppMessageStatus::Read->isTerminal());
        $this->assertFalse(WhatsAppMessageStatus::Delivered->isTerminal());
    }
}
