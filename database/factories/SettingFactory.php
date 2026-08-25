<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SettingGroup;
use App\Enums\SettingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Setting>
 */
class SettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key'   => fake()->unique()->slug(2, false),
            'value' => fake()->word(),
            'group' => SettingGroup::General->value,
            'type'  => SettingType::Text->value,
        ];
    }

    public function inGroup(SettingGroup $group): static
    {
        return $this->state(fn (): array => ['group' => $group->value]);
    }

    public function ofType(SettingType $type): static
    {
        return $this->state(fn (): array => ['type' => $type->value]);
    }
}
