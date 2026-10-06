<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
final class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'user_id' => null,
            'action' => 'test.action',
            'description' => fake()->sentence(),
            'before_values' => ['state' => 'before'],
            'after_values' => ['state' => 'after'],
            'metadata' => ['request_id' => fake()->uuid()],
            'ip_address' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }

    public function forTenant(?Tenant $tenant = null): static
    {
        return $this->for($tenant ?? Tenant::factory(), 'tenant');
    }

    public function byUser(?User $user = null): static
    {
        return $this->for($user ?? User::factory(), 'user');
    }
}
