<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\TenantNotResolvedException;
use App\Models\Tenant;
use App\Services\TenantContextService;
use PHPUnit\Framework\TestCase;

final class TenantContextServiceTest extends TestCase
{
    public function test_context_lifecycle(): void
    {
        $tenant = new Tenant(['name' => 'Acme']);
        $tenant->setAttribute('id', 42);
        $context = new TenantContextService;
        $this->assertFalse($context->check());
        $context->set($tenant);
        $this->assertTrue($context->check());
        $this->assertSame($tenant, $context->get());
        $this->assertSame(42, $context->id());
        $context->clear();
        $this->assertFalse($context->check());
    }

    public function test_get_throws_when_unresolved(): void
    {
        $this->expectException(TenantNotResolvedException::class);
        (new TenantContextService)->get();
    }
}
