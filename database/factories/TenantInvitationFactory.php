<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TenantInvitationStatus;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invitation> */
final class TenantInvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role_id' => Role::factory(),
            'token_hash' => hash('sha256', 'factory-token'),
            'status' => TenantInvitationStatus::Pending,
            'expires_at' => now()->addHours(72),
            'invited_by' => User::factory(),
            'last_sent_at' => now(),
            'send_count' => 1,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => TenantInvitationStatus::Pending, 'expires_at' => now()->addDay()]);
    }

    public function expired(): static
    {
        return $this->state(['status' => TenantInvitationStatus::Expired, 'expires_at' => now()->subMinute()]);
    }

    public function accepted(): static
    {
        return $this->state(['status' => TenantInvitationStatus::Accepted, 'accepted_at' => now()]);
    }

    public function revoked(): static
    {
        return $this->state(['status' => TenantInvitationStatus::Revoked, 'revoked_at' => now()]);
    }
}
