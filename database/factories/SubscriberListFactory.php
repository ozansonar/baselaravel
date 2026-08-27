<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\SubscriberList>
 */
class SubscriberListFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name'        => Str::title($name),
            'slug'        => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 100000),
            'description' => null,
            'is_default'  => false,
            'sort_order'  => 0,
        ];
    }

    /**
     * Site formundan gelenlerin düştüğü liste.
     */
    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
