<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BlogPost;
use App\Models\BlogPostFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogPostFile>
 */
class BlogPostFileFactory extends Factory
{
    protected $model = BlogPostFile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = $this->faker->randomElement(['pdf', 'xlsx', 'png', 'mp4', 'pptx']);
        $slug = $this->faker->unique()->slug(2);

        return [
            'blog_post_id'  => BlogPost::factory(),
            'path'          => "blog-files/{$slug}.{$extension}",
            'original_name' => $this->faker->words(2, true) . '.' . $extension,
            'extension'     => $extension,
            'mime_type'     => 'application/octet-stream',
            'size'          => $this->faker->numberBetween(1024, 5_242_880),
            'sort_order'    => 0,
        ];
    }

    /**
     * Henüz bir içeriğe bağlanmamış, kaydedilmeyi bekleyen yükleme.
     */
    public function pending(): self
    {
        return $this->state(fn (): array => [
            'blog_post_id' => null,
            'token'        => $this->faker->uuid(),
        ]);
    }
}
