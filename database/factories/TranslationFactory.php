<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Translation>
 */
class TranslationFactory extends Factory
{
    protected $model = Translation::class;

    public function definition(): array
    {
        return [
            'locale' => 'tr',
            'group'  => 'site',
            // Unique so the (locale, group, key) index does not collide when a
            // test asks the factory for several rows.
            'key'    => 'nav.' . $this->faker->unique()->word(),
            'value'  => $this->faker->words(2, true),
        ];
    }
}
