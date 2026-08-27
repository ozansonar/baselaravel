<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriberStatus;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscriber>
 */
class SubscriberFactory extends Factory
{
    protected $model = Subscriber::class;

    public function definition(): array
    {
        return [
            'email'             => $this->faker->unique()->safeEmail(),
            'first_name'        => $this->faker->firstName(),
            'last_name'         => $this->faker->lastName(),
            'locale'            => 'tr',
            'status'            => SubscriberStatus::Subscribed,
            'source'            => 'form',
            'unsubscribe_token' => Str::lower(Str::random(64)),
            'subscribed_at'     => now(),
        ];
    }

    public function unsubscribed(): static
    {
        return $this->state(fn (): array => [
            'status'          => SubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now(),
        ]);
    }
}
