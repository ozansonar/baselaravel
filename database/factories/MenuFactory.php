<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Menu>
 */
class MenuFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'      => fake()->unique()->words(2, true),
            'location'  => 'custom',
            'is_active' => true,
        ];
    }
}
