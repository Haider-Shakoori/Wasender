<?php

namespace Tests\Feature;

use App\Contracts\TenantContext;
use App\Models\Integration;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppChatbot;
use App\Models\WhatsAppChatbotConversationState;
use App\Models\WhatsAppChatbotRule;
use App\Models\WhatsAppChatbotRuleExecution;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppConversationActivity;
use App\Models\WhatsAppConversationLabel;
use App\Models\WhatsAppConversationNote;
use App\Models\WhatsAppInboxMessage;
use App\Models\WhatsAppSavedReply;
use App\Models\WhatsAppSession;
use App\Models\WhatsAppSessionEvent;
use App\Services\Inbox\WhatsAppInboxConversationQuery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FinalReleaseCriticalBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_models_use_the_migrated_table_names(): void
    {
        $this->assertSame('whatsapp_conversations', (new WhatsAppConversation)->getTable());
        $this->assertSame('whatsapp_inbox_messages', (new WhatsAppInboxMessage)->getTable());
        $this->assertSame('whatsapp_conversation_notes', (new WhatsAppConversationNote)->getTable());
        $this->assertSame('whatsapp_conversation_labels', (new WhatsAppConversationLabel)->getTable());
        $this->assertSame('whatsapp_conversation_activities', (new WhatsAppConversationActivity)->getTable());
        $this->assertSame('whatsapp_saved_replies', (new WhatsAppSavedReply)->getTable());
        $this->assertSame('whatsapp_chatbots', (new WhatsAppChatbot)->getTable());
        $this->assertSame('whatsapp_chatbot_rules', (new WhatsAppChatbotRule)->getTable());
        $this->assertSame('whatsapp_chatbot_conversation_states', (new WhatsAppChatbotConversationState)->getTable());
        $this->assertSame('whatsapp_chatbot_rule_executions', (new WhatsAppChatbotRuleExecution)->getTable());
        $this->assertSame('whatsapp_session_events', (new WhatsAppSessionEvent)->getTable());
    }

    public function test_inbox_query_cannot_resolve_another_tenants_conversation(): void
    {
        $owner = User::factory()->create();
        $own = Tenant::factory()->for($owner, 'owner')->create();
        $foreign = Tenant::factory()->create();
        $session = WhatsAppSession::create(['tenant_id' => $foreign->id, 'name' => 'Foreign', 'storage_key' => 'wa_'.str_repeat('x', 48), 'status' => 'ready', 'created_by' => $owner->id]);
        $conversation = WhatsAppConversation::create(['tenant_id' => $foreign->id, 'whatsapp_session_id' => $session->id, 'whatsapp_address' => '15551234567@c.us', 'normalized_phone' => '+15551234567']);
        app(TenantContext::class)->set($own);

        $this->expectException(ModelNotFoundException::class);
        app(WhatsAppInboxConversationQuery::class)->find($conversation->uuid);
    }

    public function test_woocommerce_signature_cannot_authenticate_message_endpoint(): void
    {
        $tenant = Tenant::factory()->create();
        $secret = str_repeat('s', 32);
        $integration = Integration::create(['tenant_id' => $tenant->id, 'provider' => 'woocommerce', 'name' => 'Store', 'status' => 'active', 'is_enabled' => true, 'credentials_encrypted' => ['token_hash' => hash('sha256', 'token'), 'webhook_secret' => $secret]]);
        $body = json_encode(['recipient' => '+15551234567'], JSON_THROW_ON_ERROR);

        $this->call('POST', route('api.integrations.messages', $integration->uuid), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WC_WEBHOOK_SIGNATURE' => base64_encode(hash_hmac('sha256', $body, $secret, true)),
        ], $body)->assertUnauthorized();
    }
}
