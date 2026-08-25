<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\UploadedFile>
 */
class UploadedFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'original_name'  => fake()->word() . '.pdf',
            'stored_path'    => 'files/' . fake()->unique()->lexify('??????????') . '.pdf',
            'original_path'  => null,
            'mime_type'      => 'application/pdf',
            'extension'      => 'pdf',
            'file_size'      => fake()->numberBetween(1024, 5_242_880),
            'webp_size'      => null,
            'category'       => 'document',
            'title'          => fake()->sentence(3),
            'alt_text'       => null,
            'hash'           => fake()->unique()->sha256(),
            'width'          => null,
            'height'         => null,
            'download_count' => 0,
            'is_public'      => true,
            'uploaded_by'    => null,
        ];
    }
}
