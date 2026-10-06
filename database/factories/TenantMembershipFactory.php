<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TenantMembership> */
final class TenantMembershipFactory extends Factory
{
    protected $model = TenantMembership::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'role_id' => Role::factory()->system(),
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => MembershipStatus::Active, 'joined_at' => now()]);
    }

    public function invited(): static
    {
        return $this->state(['status' => MembershipStatus::Invited, 'joined_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => MembershipStatus::Suspended]);
    }

    public function removed(): static
    {
        return $this->state(['status' => MembershipStatus::Removed]);
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::query()->where('tenant_id', $attributes['tenant_id'])->where('slug', 'owner')->value('id')
                ?? Role::factory()->state(['tenant_id' => $attributes['tenant_id'], 'slug' => 'owner', 'name' => 'Owner', 'is_system' => true]),
            'status' => MembershipStatus::Active,
        ]);
    }
}
