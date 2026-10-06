<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppChatbot;
use App\Services\SubscriptionUsageRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubscriptionUsageRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatbot_and_integration_limits_use_real_tenant_usage(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $user->id]);

        WhatsAppChatbot::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Support bot',
            'status' => 'draft',
            'is_enabled' => false,
            'priority' => 100,
            'fallback_behavior' => 'none',
            'created_by' => $user->id,
        ]);

        Integration::query()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'generic_api',
            'name' => 'CRM',
            'status' => 'active',
            'is_enabled' => true,
            'configuration' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $registry = app(SubscriptionUsageRegistry::class);

        $this->assertSame(1, $registry->usage($tenant, 'chatbots.max'));
        $this->assertSame(1, $registry->usage($tenant, 'integrations.max'));
        $this->assertContains('chatbots.max', $registry->measurableKeys());
        $this->assertContains('integrations.max', $registry->measurableKeys());
    }
}
