<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CampaignRecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CampaignRecipient>
 */
class CampaignRecipientFactory extends Factory
{
    protected $model = CampaignRecipient::class;

    public function definition(): array
    {
        return [
            'campaign_id'       => Campaign::factory(),
            'email'             => $this->faker->unique()->safeEmail(),
            'first_name'        => $this->faker->firstName(),
            'last_name'         => $this->faker->lastName(),
            'locale'            => 'tr',
            'status'            => CampaignRecipientStatus::Pending,
            'unsubscribe_token' => Str::lower(Str::random(64)),
            'attempts'          => 0,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status'   => CampaignRecipientStatus::Sent,
            'sent_at'  => now(),
            'attempts' => 1,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status'   => CampaignRecipientStatus::Failed,
            'attempts' => 3,
            'error'    => 'SMTP bağlantısı kurulamadı',
        ]);
    }
}
