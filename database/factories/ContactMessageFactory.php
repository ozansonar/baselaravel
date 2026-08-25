<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'       => fake()->name(),
            'email'      => fake()->safeEmail(),
            'phone'      => '05' . fake()->numerify('#########'),
            'subject'    => fake()->sentence(4),
            'message'    => fake()->paragraph(),
            'is_read'    => false,
            'read_at'    => null,
            'replied_at' => null,
            'reply_text' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => [
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function replied(): static
    {
        return $this->read()->state(fn (): array => [
            'replied_at' => now(),
            'reply_text' => fake()->paragraph(),
        ]);
    }
}
