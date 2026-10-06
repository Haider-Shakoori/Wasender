<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\TenantSlugService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantSlugServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_collisions_increment_deterministically(): void
    {
        Tenant::factory()->create(['slug' => 'wesoft-technologies']);
        Tenant::factory()->create(['slug' => 'wesoft-technologies-2']);
        $this->assertSame('wesoft-technologies-3', app(TenantSlugService::class)->generate('WeSoft Technologies'));
    }

    public function test_non_slug_company_name_gets_non_numeric_safe_fallback(): void
    {
        $slug = app(TenantSlugService::class)->generate('🎉🎉');
        $this->assertMatchesRegularExpression('/^workspace-[a-z0-9]{8}$/', $slug);
        $this->assertLessThanOrEqual(100, strlen($slug));
    }
}
