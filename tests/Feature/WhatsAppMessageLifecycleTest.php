<?php

namespace Tests\Feature;

use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Enums\WhatsAppSessionStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppMessageLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class WhatsAppMessageLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_acknowledgements_never_regress(): void
    {
        $message = $this->message(WhatsAppMessageStatus::Sent);
        $service = app(WhatsAppMessageLifecycleService::class);
        $message = $service->acknowledge($message, WhatsAppMessageStatus::Read, fake()->uuid());
        $message = $service->acknowledge($message, WhatsAppMessageStatus::Delivered, fake()->uuid());

        $this->assertSame(WhatsAppMessageStatus::Read, $message->refresh()->status);
        $this->assertDatabaseCount('whatsapp_message_events', 1);
    }

    public function test_cancellation_after_transport_acceptance_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(WhatsAppMessageLifecycleService::class)->transition($this->message(WhatsAppMessageStatus::Sent), WhatsAppMessageStatus::Cancelled, 'user');
    }

    private function message(WhatsAppMessageStatus $status): WhatsAppMessage
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $user->id]);
        $session = WhatsAppSession::create(['tenant_id' => $tenant->id, 'name' => 'Ready', 'storage_key' => 'wa_'.str_repeat('a', 48), 'status' => WhatsAppSessionStatus::Ready, 'created_by' => $user->id]);

        return WhatsAppMessage::create(['tenant_id' => $tenant->id, 'whatsapp_session_id' => $session->id, 'created_by' => $user->id,
            'recipient' => '+15551234567', 'recipient_normalized' => '15551234567', 'message_type' => WhatsAppMessageType::Text,
            'body' => 'Test', 'status' => $status, 'payload_hash' => hash('sha256', 'test'), 'max_attempts' => 3]);
    }
}
