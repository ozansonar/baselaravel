<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\MailTemplate>
 */
class MailTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key'         => fake()->unique()->slug(2, false),
            'name'        => fake()->words(2, true),
            'description' => fake()->sentence(),
            'subject'     => fake()->sentence(4),
            'body'        => '<p>' . fake()->paragraph() . '</p>',
            'variables'   => [['key' => 'site_name', 'label' => 'Site Adı', 'example' => 'Acme']],
            'is_active'   => true,
        ];
    }
}
