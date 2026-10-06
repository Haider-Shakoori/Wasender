<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Role> */
final class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return ['tenant_id' => null, 'name' => $name, 'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'), 'is_system' => false];
    }

    public function system(): static
    {
        return $this->state(['tenant_id' => null, 'is_system' => true]);
    }

    public function tenantScoped(?Tenant $tenant = null): static
    {
        return $this->state(['tenant_id' => $tenant?->id ?? Tenant::factory(), 'is_system' => false]);
    }
}
