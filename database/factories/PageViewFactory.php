<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PageView>
 */
class PageViewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'url'             => fake()->url(),
            'url_path'        => '/' . fake()->slug(2),
            'ip_address'      => fake()->ipv4(),
            'ip_masked'       => false,
            'user_agent'      => fake()->userAgent(),
            'device_type'     => fake()->randomElement(['desktop', 'mobile', 'tablet']),
            'browser'         => fake()->randomElement(['Chrome', 'Safari', 'Firefox']),
            'browser_version' => (string) fake()->numberBetween(100, 140),
            'os'              => fake()->randomElement(['macOS', 'Windows', 'iOS', 'Android']),
            'referrer'        => null,
            'referrer_domain' => null,
            'session_id'      => fake()->uuid(),
            'user_id'         => null,
            'is_bot'          => false,
            'bot_name'        => null,
            'screen_width'    => 1920,
            'screen_height'   => 1080,
            'viewed_at'       => now(),
        ];
    }

    public function bot(): static
    {
        return $this->state(fn (): array => [
            'is_bot'   => true,
            'bot_name' => 'Googlebot',
        ]);
    }
}
