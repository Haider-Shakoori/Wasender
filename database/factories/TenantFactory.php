<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tenant> */
final class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'email' => fake()->companyEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'country' => fake()->countryCode(),
            'timezone' => config('saas.default_timezone'),
            'currency' => config('saas.default_currency'),
            'locale' => config('saas.default_locale'),
            'status' => TenantStatus::Active,
            'is_active' => true,
            'owner_id' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => TenantStatus::Active, 'is_active' => true]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => TenantStatus::Suspended, 'is_active' => false]);
    }

    public function pending(): static
    {
        return $this->state(['status' => TenantStatus::Pending, 'is_active' => false]);
    }
}
