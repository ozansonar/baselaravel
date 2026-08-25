<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Every model ships a working factory.
 *
 * Derived projects lean on these for their own tests and demo data, so a
 * factory that no longer matches its table has to fail here rather than in
 * whatever project copied the kit.
 */
class ModelFactoriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, class-string<Model>>
     */
    private static function modelClasses(): array
    {
        $classes = [];

        /** @var SplFileInfo $file */
        // Data providers run before the application is booted, so the path is
        // resolved from this file rather than through app_path().
        $modelsDir = dirname(__DIR__, 2) . '/app/Models';

        foreach (Finder::create()->files()->in($modelsDir)->name('*.php') as $file) {
            $class = 'App\\Models\\' . $file->getBasename('.php');

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return array<string, array{0: class-string<Model>}>
     */
    public static function models(): array
    {
        $cases = [];

        foreach (self::modelClasses() as $class) {
            $cases[class_basename($class)] = [$class];
        }

        return $cases;
    }

    /**
     * @param class-string<Model> $class
     */
    #[DataProvider('models')]
    public function test_model_has_a_factory(string $class): void
    {
        $traits = [];
        $reflection = new ReflectionClass($class);

        while ($reflection !== false) {
            $traits = array_merge($traits, $reflection->getTraitNames());
            $reflection = $reflection->getParentClass();
        }

        $this->assertContains(
            HasFactory::class,
            $traits,
            class_basename($class) . ' modelinde HasFactory trait i yok',
        );

        $this->assertTrue(
            class_exists('Database\\Factories\\' . class_basename($class) . 'Factory'),
            class_basename($class) . 'Factory sınıfı yok',
        );
    }

    /**
     * @param class-string<Model> $class
     */
    #[DataProvider('models')]
    public function test_factory_creates_a_persisted_record(string $class): void
    {
        /** @var Model $model */
        $model = $class::factory()->create();

        $this->assertTrue($model->exists, class_basename($class) . ' kaydedilmedi');
        $this->assertDatabaseHas($model->getTable(), [$model->getKeyName() => $model->getKey()]);
    }

    /**
     * Factories are also used to build several rows at once, which is where
     * unique columns usually blow up.
     */
    #[DataProvider('models')]
    public function test_factory_can_create_several_rows(string $class): void
    {
        $created = $class::factory()->count(3)->create();

        $this->assertCount(3, $created);
    }
}
