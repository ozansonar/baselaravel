<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConsentCategory;
use App\Services\ConsentService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Consent>
 */
class ConsentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token'      => (string) Str::uuid(),
            'categories' => [ConsentCategory::Analytics->value],
            'version'    => ConsentService::VERSION,
            'user_id'    => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'url'        => fake()->url(),
        ];
    }

    /** Hiçbir isteğe bağlı kategoriye izin verilmemiş kayıt. */
    public function refused(): static
    {
        return $this->state(fn (array $attributes): array => ['categories' => []]);
    }

    /** Tüm isteğe bağlı kategorilere izin verilmiş kayıt. */
    public function acceptedAll(): static
    {
        return $this->state(fn (array $attributes): array => [
            'categories' => array_map(
                static fn (ConsentCategory $case): string => $case->value,
                ConsentCategory::optional(),
            ),
        ]);
    }
}
