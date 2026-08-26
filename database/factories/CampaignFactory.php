<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CampaignAudience;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'name'            => $this->faker->sentence(3),
            'subject'         => $this->faker->sentence(5),
            'body'            => '<p>' . $this->faker->paragraph() . '</p>',
            'audience'        => CampaignAudience::Subscribers,
            'audience_filter' => [],
            'status'          => CampaignStatus::Draft,
            'throttled'       => true,
        ];
    }

    public function sending(): static
    {
        return $this->state(fn (): array => [
            'status'     => CampaignStatus::Sending,
            'started_at' => now(),
        ]);
    }

    /**
     * A hand-typed recipient list, which needs no other rows to exist.
     *
     * @param array<int, array{name: ?string, email: string}> $recipients
     */
    public function manual(array $recipients): static
    {
        return $this->state(fn (): array => [
            'audience'        => CampaignAudience::Manual,
            'audience_filter' => ['recipients' => $recipients],
        ]);
    }
}
