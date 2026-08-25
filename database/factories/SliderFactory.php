<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Slider>
 */
class SliderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title'       => fake()->sentence(3),
            'subtitle'    => fake()->sentence(8),
            'image'       => 'sliders/ornek.webp',
            'button_text' => 'Detay',
            'button_url'  => '/blog',
            'sort_order'  => fake()->numberBetween(0, 20),
            'is_active'   => true,
        ];
    }
}
