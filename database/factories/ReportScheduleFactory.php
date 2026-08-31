<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReportFrequency;
use App\Enums\ReportType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ReportSchedule>
 */
class ReportScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type'       => fake()->randomElement(ReportType::cases())->value,
            'frequency'  => fake()->randomElement(ReportFrequency::cases())->value,
            'range'      => '30',
            'format'     => 'excel',
            'recipients' => [fake()->safeEmail()],
            'is_active'  => true,
            'user_id'    => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
