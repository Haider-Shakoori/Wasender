<?php

namespace Tests\Feature;

use App\Enums\WhatsAppSessionStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppSessionPacer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class WhatsAppSessionPacerTest extends TestCase
{
    use RefreshDatabase;

    public function test_pacing_is_reserved_per_session_without_blocking_a_worker(): void
    {
        Carbon::setTestNow('2026-10-06 12:00:00.000');
        config([
            'whatsapp_messages.send_delay_min_ms' => 6000,
            'whatsapp_messages.send_delay_max_ms' => 6000,
        ]);

        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $user->id]);
        $session = WhatsAppSession::create([
            'tenant_id' => $tenant->id,
            'name' => 'Primary',
            'storage_key' => 'wa_'.str_repeat('a', 48),
            'status' => WhatsAppSessionStatus::Ready,
            'created_by' => $user->id,
        ]);

        $pacer = app(WhatsAppSessionPacer::class);

        $this->assertSame(0, $pacer->reserve($session));
        $this->assertSame(6000, $pacer->reserve($session->refresh()));

        Carbon::setTestNow(now()->addSeconds(6));

        $this->assertSame(0, $pacer->reserve($session->refresh()));
    }
}
