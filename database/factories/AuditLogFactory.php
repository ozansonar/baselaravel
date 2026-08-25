<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'        => null,
            'event'          => AuditEvent::Custom,
            'auditable_type' => null,
            'auditable_id'   => null,
            'label'          => fake()->sentence(5),
            'old_values'     => null,
            'new_values'     => null,
            'ip_address'     => fake()->ipv4(),
            'user_agent'     => fake()->userAgent(),
            'url'            => fake()->url(),
            'created_at'     => now(),
        ];
    }
}
