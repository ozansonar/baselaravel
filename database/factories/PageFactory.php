<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title'            => fake()->unique()->sentence(3),
            'slug'             => fake()->unique()->slug(3),
            'content'          => '<p>' . implode('</p><p>', fake()->paragraphs(3)) . '</p>',
            'sections'         => null,
            'excerpt'          => fake()->sentence(12),
            'image'            => null,
            'status'           => ContentStatus::Published,
            'sort_order'       => fake()->numberBetween(0, 50),
            'meta_title'       => fake()->sentence(4),
            'meta_description' => fake()->sentence(12),
            'published_at'     => now()->subDays(fake()->numberBetween(0, 30)),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status'       => ContentStatus::Draft,
            'published_at' => null,
        ]);
    }
}
