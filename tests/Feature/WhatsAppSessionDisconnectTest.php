<?php

namespace Tests\Feature;

use App\Contracts\Messaging\MessagingConnector;
use App\Enums\WhatsAppSessionStatus;
use App\Jobs\ManageWhatsAppSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppSessionLifecycleService;
use App\Services\WhatsAppSessionRuntimeEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class WhatsAppSessionDisconnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_disconnect_preserves_auth_by_using_connector_disconnect_not_logout(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $user->id]);
        $session = WhatsAppSession::create([
            'tenant_id' => $tenant->id,
            'name' => 'Support',
            'storage_key' => 'wa_'.str_repeat('d', 48),
            'status' => WhatsAppSessionStatus::Ready,
            'created_by' => $user->id,
        ]);

        $connector = Mockery::mock(MessagingConnector::class);
        $connector->shouldReceive('disconnect')->once()->with($session->uuid);
        $connector->shouldNotReceive('logout');

        (new ManageWhatsAppSession($session->id, 'disconnect'))
            ->handle(
                $connector,
                app(WhatsAppSessionLifecycleService::class),
                app(WhatsAppSessionRuntimeEligibility::class),
            );

        $this->assertSame(WhatsAppSessionStatus::Disconnected, $session->refresh()->status);
        $this->assertSame('manual_disconnect', $session->events()->latest('id')->firstOrFail()->reason_code);
    }
}
