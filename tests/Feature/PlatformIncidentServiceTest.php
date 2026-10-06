<?php

namespace Tests\Feature;

use App\Models\PlatformIncident;
use App\Services\PlatformIncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlatformIncidentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_incidents_are_deduplicated_and_can_be_resolved(): void
    {
        config(['operations.alert_email' => null]);

        $service = app(PlatformIncidentService::class);

        $first = $service->raise('health:connector', 'critical', 'Connector offline', 'The connector is unavailable.');
        $second = $service->raise('health:connector', 'critical', 'Connector offline', 'The connector is still unavailable.');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('platform_incidents', 1);
        $this->assertSame(2, $second->refresh()->occurrences);
        $this->assertSame('open', $second->status);

        $service->resolve('health:connector');

        $incident = PlatformIncident::firstOrFail();
        $this->assertSame('resolved', $incident->status);
        $this->assertNotNull($incident->resolved_at);
    }
}
