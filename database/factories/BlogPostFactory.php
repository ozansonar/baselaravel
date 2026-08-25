<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BlogCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\BlogPost>
 */
class BlogPostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blog_category_id' => BlogCategory::factory(),
            'user_id'          => User::factory(),
            'title'            => fake()->unique()->sentence(4),
            'slug'             => fake()->unique()->slug(4),
            'excerpt'          => fake()->paragraph(),
            'body'             => '<p>' . implode('</p><p>', fake()->paragraphs(4)) . '</p>',
            'image'            => null,
            'meta_title'       => fake()->sentence(4),
            'meta_description' => fake()->sentence(12),
            'is_published'     => true,
            'published_at'     => now()->subDays(fake()->numberBetween(0, 60)),
            'views'            => fake()->numberBetween(0, 5000),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    public function withoutAuthor(): static
    {
        return $this->state(fn (): array => ['user_id' => null]);
    }
}
