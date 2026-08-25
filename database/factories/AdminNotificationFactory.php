<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\AdminNotification>
 */
class AdminNotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'    => null,
            'type'       => fake()->slug(1),
            'level'      => NotificationLevel::Info,
            'title'      => fake()->sentence(4),
            'message'    => fake()->sentence(10),
            'icon'       => null,
            'action_url' => null,
            'read_at'    => null,
            'created_at' => now(),
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }

    public function critical(): static
    {
        return $this->state(fn (): array => ['level' => NotificationLevel::Critical]);
    }
}
