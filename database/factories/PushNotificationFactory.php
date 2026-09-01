<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PushAudience;
use App\Enums\PushNotificationStatus;
use App\Models\PushNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushNotification>
 */
class PushNotificationFactory extends Factory
{
    protected $model = PushNotification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title'       => $this->faker->sentence(3),
            'body'        => $this->faker->sentence(10),
            'link'        => null,
            'audience'    => PushAudience::All,
            'audience_id' => null,
            'status'      => PushNotificationStatus::Queued,
        ];
    }

    public function sending(): static
    {
        return $this->state(fn (): array => [
            'status'     => PushNotificationStatus::Sending,
            'started_at' => now(),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status'        => PushNotificationStatus::Sent,
            'started_at'    => now()->subMinutes(10),
            'completed_at'  => now(),
            'total_devices' => 3,
            'sent_count'    => 3,
        ]);
    }

    public function forRole(int $roleId): static
    {
        return $this->state(fn (): array => [
            'audience'    => PushAudience::Role,
            'audience_id' => $roleId,
        ]);
    }

    public function forUser(int $userId): static
    {
        return $this->state(fn (): array => [
            'audience'    => PushAudience::User,
            'audience_id' => $userId,
        ]);
    }
}
