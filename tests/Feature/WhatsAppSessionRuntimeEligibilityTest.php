<?php

namespace Tests\Feature;

use App\Enums\TenantSubscriptionStatus;
use App\Enums\WhatsAppSessionStatus;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppSessionRuntimeEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WhatsAppSessionRuntimeEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_subscription_allows_runtime(): void
    {
        [$tenant, $session] = $this->sessionWithSubscription(TenantSubscriptionStatus::Active, now()->addMonth());

        $this->assertTrue(app(WhatsAppSessionRuntimeEligibility::class)->eligible($session));
    }

    public function test_expired_period_blocks_runtime(): void
    {
        [$tenant, $session] = $this->sessionWithSubscription(TenantSubscriptionStatus::Active, now()->subMinute());

        $this->assertFalse(app(WhatsAppSessionRuntimeEligibility::class)->eligible($session));
    }

    public function test_suspended_tenant_blocks_runtime_even_with_active_subscription(): void
    {
        [$tenant, $session] = $this->sessionWithSubscription(TenantSubscriptionStatus::Active, now()->addMonth());
        $tenant->update(['is_active' => false]);

        $this->assertFalse(app(WhatsAppSessionRuntimeEligibility::class)->eligible($session->refresh()));
    }

    private function sessionWithSubscription(TenantSubscriptionStatus $status, $periodEndsAt): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $user->id]);

        TenantSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => $status,
            'is_current' => true,
            'current_period_ends_at' => $periodEndsAt,
        ]);

        $session = WhatsAppSession::create([
            'tenant_id' => $tenant->id,
            'name' => 'Support',
            'storage_key' => 'wa_'.str_repeat('e', 48),
            'status' => WhatsAppSessionStatus::Ready,
            'created_by' => $user->id,
        ]);

        return [$tenant, $session];
    }
}
