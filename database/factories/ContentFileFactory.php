<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BlogPost;
use App\Models\ContentFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentFile>
 */
class ContentFileFactory extends Factory
{
    protected $model = ContentFile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = $this->faker->randomElement(['pdf', 'xlsx', 'png', 'mp4', 'pptx']);
        $slug = $this->faker->unique()->slug(2);

        return [
            // Varsayılan bağ blog yazısı; sayfa eki isteyen `for()` ile
            // kendi içeriğini verir.
            'attachable_type' => BlogPost::class,
            'attachable_id'   => BlogPost::factory(),
            'path'            => "blog-files/{$slug}.{$extension}",
            'original_name'   => $this->faker->words(2, true) . '.' . $extension,
            'extension'       => $extension,
            'mime_type'       => 'application/octet-stream',
            'size'            => $this->faker->numberBetween(1024, 5_242_880),
            'sort_order'      => 0,
        ];
    }

    /**
     * Henüz bir içeriğe bağlanmamış, kaydedilmeyi bekleyen yükleme.
     */
    public function pending(): self
    {
        return $this->state(fn (): array => [
            'attachable_type' => null,
            'attachable_id'   => null,
            'token'           => $this->faker->uuid(),
        ]);
    }
}
