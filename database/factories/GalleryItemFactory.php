<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GalleryType;
use App\Models\GalleryCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\GalleryItem>
 */
class GalleryItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title'               => fake()->sentence(3),
            'description'         => fake()->sentence(10),
            'image'               => 'gallery/ornek.webp',
            'type'                => GalleryType::Photo,
            'gallery_category_id' => GalleryCategory::factory(),
            'video_url'           => null,
            'duration'            => null,
            'view_count'          => fake()->numberBetween(0, 500),
            'sort_order'          => fake()->numberBetween(0, 50),
            'is_active'           => true,
        ];
    }

    public function video(): static
    {
        return $this->state(fn (): array => [
            'type'      => GalleryType::Video,
            'video_url' => 'https://www.youtube.com/watch?v=' . fake()->lexify('???????????'),
            'duration'  => fake()->numberBetween(30, 900),
        ]);
    }
}
