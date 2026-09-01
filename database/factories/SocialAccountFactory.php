<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'  => User::factory(),
            'provider' => SocialProvider::Google,
            // Sağlayıcının kullanıcı kimliği benzersiz olmalı: tablo
            // (provider, provider_user_id) çiftini tekil tutuyor.
            'provider_user_id' => (string) $this->faker->unique()->numerify('##################'),
            'email'            => $this->faker->unique()->safeEmail(),
            'last_login_at'    => now(),
        ];
    }

    public function apple(): static
    {
        return $this->state(fn (): array => ['provider' => SocialProvider::Apple]);
    }
}
