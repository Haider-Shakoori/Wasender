<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Permission> */
final class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $slug = 'test.'.fake()->unique()->slug(2);

        return ['name' => str($slug)->replace('.', ' ')->title(), 'slug' => $slug, 'description' => fake()->sentence()];
    }
}
