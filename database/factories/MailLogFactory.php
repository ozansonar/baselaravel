<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MailLogStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\MailLog>
 */
class MailLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'to'             => fake()->safeEmail(),
            'cc'             => null,
            'bcc'            => null,
            'from'           => fake()->safeEmail(),
            'reply_to'       => null,
            'subject'        => fake()->sentence(4),
            'body'           => '<p>' . fake()->paragraph() . '</p>',
            'mailable_class' => 'App\\Mail\\TestMail',
            'status'         => MailLogStatus::Sent,
            'error_message'  => null,
            'sent_at'        => now(),
            'metadata'       => null,
            'ip_address'     => fake()->ipv4(),
            'user_id'        => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status'        => MailLogStatus::Failed,
            'sent_at'       => null,
            'error_message' => 'SMTP bağlantısı kurulamadı.',
        ]);
    }
}
