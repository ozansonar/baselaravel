<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Language>
 */
class LanguageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code'        => fake()->unique()->lexify('??'),
            'name'        => fake()->country(),
            'native_name' => fake()->country(),
            'flag'        => '🏳️',
            'is_active'   => true,
            'is_default'  => false,
            'sort_order'  => fake()->numberBetween(0, 20),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
