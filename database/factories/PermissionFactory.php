<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PermissionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key'        => fake()->unique()->slug(2) . '.' . fake()->randomElement(['view', 'manage', 'delete']),
            'name'       => fake()->sentence(3),
            'group'      => PermissionGroup::Content->value,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function inGroup(PermissionGroup $group): static
    {
        return $this->state(fn (): array => ['group' => $group->value]);
    }
}
