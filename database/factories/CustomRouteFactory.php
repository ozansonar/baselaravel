<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CustomRouteType;
use App\Models\CustomRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomRoute>
 */
final class CustomRouteFactory extends Factory
{
    protected $model = CustomRoute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locale'        => 'tr',
            'slug'          => $this->faker->unique()->slug(2),
            'target_route'  => 'contact',
            'target_params' => [],
            'type'          => CustomRouteType::Render,
            'is_active'     => true,
            'note'          => null,
        ];
    }

    public function forAllLanguages(): self
    {
        return $this->state(fn (): array => ['locale' => null]);
    }

    public function redirecting(): self
    {
        return $this->state(fn (): array => ['type' => CustomRouteType::MovedPermanently]);
    }
}
