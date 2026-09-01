<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ErrorLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ErrorLog>
 */
final class ErrorLogFactory extends Factory
{
    protected $model = ErrorLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $file = base_path('app/Services/' . $this->faker->word() . 'Service.php');
        $line = $this->faker->numberBetween(10, 400);
        $exception = $this->faker->randomElement([
            'RuntimeException',
            'InvalidArgumentException',
            'Illuminate\\Database\\QueryException',
            'TypeError',
        ]);

        $seenAt = $this->faker->dateTimeBetween('-20 days');

        return [
            // Parmak izi gerçek kayıttakiyle aynı kuralla üretiliyor: tür +
            // dosya + satır. Benzersiz sütun olduğu için uydurma bir değer
            // ikinci kayıtta çakışırdı.
            'fingerprint'   => md5($exception . '|' . $file . ':' . $line),
            'exception'     => $exception,
            'message'       => $this->faker->sentence(),
            'file'          => $file,
            'line'          => $line,
            'trace'         => "#0 {$file}({$line}): " . $this->faker->word() . "()\n#1 {main}",
            'url'           => $this->faker->url(),
            'method'        => $this->faker->randomElement(['GET', 'POST']),
            'ip_address'    => $this->faker->ipv4(),
            'user_agent'    => $this->faker->userAgent(),
            'user_id'       => null,
            'occurrences'   => $this->faker->numberBetween(1, 250),
            'first_seen_at' => $this->faker->dateTimeBetween('-60 days', $seenAt),
            'last_seen_at'  => $seenAt,
            'resolved_at'   => null,
            'resolved_by'   => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => ['resolved_at' => now()]);
    }

    /**
     * Konsoldan ya da zamanlanmış görevden gelen hata: istek bilgisi yok.
     */
    public function fromConsole(): static
    {
        return $this->state(fn (): array => [
            'url'        => null,
            'method'     => null,
            'ip_address' => null,
            'user_agent' => null,
        ]);
    }
}
