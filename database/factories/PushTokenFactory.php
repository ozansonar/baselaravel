<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PushPlatform;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\PushToken>
 */
class PushTokenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'token'        => Str::random(140),
            'platform'     => fake()->randomElement(PushPlatform::cases())->value,
            'device_name'  => fake()->randomElement(['iPhone 15', 'Pixel 8', 'Chrome']),
            'last_used_at' => now(),
        ];
    }
}
