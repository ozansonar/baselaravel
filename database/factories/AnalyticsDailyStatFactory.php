<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\AnalyticsDailyStat>
 */
class AnalyticsDailyStatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date'            => fake()->unique()->dateTimeBetween('-1 year', 'yesterday')->format('Y-m-d'),
            'total_views'     => fake()->numberBetween(0, 5000),
            'unique_visitors' => fake()->numberBetween(0, 2000),
            'bot_views'       => fake()->numberBetween(0, 500),
            'desktop_views'   => fake()->numberBetween(0, 2000),
            'mobile_views'    => fake()->numberBetween(0, 2000),
            'tablet_views'    => fake()->numberBetween(0, 300),
            'top_pages'       => [],
            'top_referrers'   => [],
            'top_browsers'    => [],
        ];
    }
}
