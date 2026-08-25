<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PopupPage;
use App\Enums\PopupSize;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Popup>
 */
class PopupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title'       => fake()->sentence(3),
            'description' => fake()->sentence(12),
            'image'       => null,
            'button_text' => 'İncele',
            'button_url'  => '/blog',
            'size'        => PopupSize::Md,
            'pages'       => [PopupPage::Home->value],
            'start_date'  => now()->subDay(),
            'end_date'    => now()->addMonth(),
            'is_active'   => true,
            'sort_order'  => fake()->numberBetween(0, 20),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'start_date' => now()->subMonths(2),
            'end_date'   => now()->subMonth(),
        ]);
    }
}
