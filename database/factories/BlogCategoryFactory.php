<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\BlogCategory>
 */
class BlogCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'       => fake()->unique()->words(2, true),
            'slug'       => fake()->unique()->slug(2),
            'icon'       => 'bi-bookmark',
            'is_active'  => true,
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
