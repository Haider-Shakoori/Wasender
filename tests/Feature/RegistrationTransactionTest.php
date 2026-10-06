<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\RoleInitializer;
use App\Data\Auth\RegisterTenantOwnerData;
use App\Models\Tenant;
use App\Services\RegisterTenantOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use RuntimeException;
use Tests\TestCase;

final class RegistrationTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_initialization_failure_rolls_back_every_registration_record(): void
    {
        $this->app->bind(RoleInitializer::class, fn () => new class implements RoleInitializer
        {
            public function initializeForTenant(Tenant $tenant): Collection
            {
                throw new RuntimeException('Controlled role failure.');
            }
        });

        try {
            $this->app->make(RegisterTenantOwner::class)->execute(
                new RegisterTenantOwnerData('Ada', 'ada@example.test', 'Acme', 'StrongPass123'),
            );
            $this->fail('Expected registration to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Controlled role failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('tenant_user', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
