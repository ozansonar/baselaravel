<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommentStatus;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\BlogComment>
 */
class BlogCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blog_post_id' => BlogPost::factory(),
            'parent_id'    => null,
            'name'         => fake()->name(),
            'email'        => fake()->safeEmail(),
            'body'         => fake()->paragraph(),
            'status'       => CommentStatus::Pending,
            'ip_address'   => fake()->ipv4(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => CommentStatus::Approved]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['status' => CommentStatus::Rejected]);
    }
}
