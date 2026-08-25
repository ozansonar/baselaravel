<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RedirectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Redirect>
 */
class RedirectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'old_url'     => '/' . fake()->unique()->slug(2),
            'new_url'     => '/' . fake()->slug(2),
            'status_code' => RedirectStatus::MovedPermanently,
            'hit_count'   => fake()->numberBetween(0, 200),
            'last_hit_at' => null,
            'is_active'   => true,
            'note'        => null,
        ];
    }

    public function gone(): static
    {
        return $this->state(fn (): array => [
            'status_code' => RedirectStatus::Gone,
            'new_url'     => null,
        ]);
    }
}
