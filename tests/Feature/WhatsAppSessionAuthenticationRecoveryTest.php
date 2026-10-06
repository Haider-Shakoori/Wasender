<?php

namespace Tests\Feature;

use App\Enums\WhatsAppSessionStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppSessionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WhatsAppSessionAuthenticationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_pending_session_can_move_directly_to_authenticated_and_ready(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $user->id]);
        $session = WhatsAppSession::create([
            'tenant_id' => $tenant->id,
            'name' => 'Canary',
            'storage_key' => 'wa_'.str_repeat('r', 48),
            'status' => WhatsAppSessionStatus::QrPending,
            'created_by' => $user->id,
        ]);

        $lifecycle = app(WhatsAppSessionLifecycleService::class);
        $authenticated = $lifecycle->transition($session, WhatsAppSessionStatus::Authenticated, 'connector');
        $this->assertSame(WhatsAppSessionStatus::Authenticated, $authenticated->status);
        $this->assertNotNull($authenticated->authenticated_at);

        $ready = $lifecycle->transition($authenticated, WhatsAppSessionStatus::Ready, 'connector');
        $this->assertSame(WhatsAppSessionStatus::Ready, $ready->status);
        $this->assertNotNull($ready->ready_at);
        $this->assertNotNull($ready->last_seen_at);
    }

    public function test_reconnecting_session_can_restore_through_authenticated(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $user->id]);
        $session = WhatsAppSession::create([
            'tenant_id' => $tenant->id,
            'name' => 'Recovered',
            'storage_key' => 'wa_'.str_repeat('s', 48),
            'status' => WhatsAppSessionStatus::Reconnecting,
            'created_by' => $user->id,
        ]);

        $restored = app(WhatsAppSessionLifecycleService::class)
            ->transition($session, WhatsAppSessionStatus::Authenticated, 'connector');

        $this->assertSame(WhatsAppSessionStatus::Authenticated, $restored->status);
    }
}
