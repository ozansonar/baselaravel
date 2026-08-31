<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\UserNotificationPreference>
 */
class UserNotificationPreferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type'    => fake()->randomElement(NotificationPreference::cases())->value,
            'enabled' => true,
        ];
    }

    /** Kişinin kapattığı bir tür. */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => ['enabled' => false]);
    }
}
