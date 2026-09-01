<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ContentRevision;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentRevision>
 */
class ContentRevisionFactory extends Factory
{
    protected $model = ContentRevision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'revisionable_type' => Page::class,
            'revisionable_id'   => Page::factory(),
            'locale'            => 'tr',
            'user_id'           => null,
            'payload'           => ['title' => $this->faker->sentence(3)],
        ];
    }
}
