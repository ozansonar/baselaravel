<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The project has no build step and is meant to keep it that way.
 *
 * Vendor libraries live as ready files under public/assets/vendor/ and are
 * included with asset(); cache busting comes from versioned_asset(), which
 * stamps the file's mtime rather than a bundler hash.
 *
 * This matters most for the base kit: every project cloned from here can
 * accidentally pull the toolchain back in — an artisan scaffolding command, a
 * file copied from a stock Laravel skeleton, a package that publishes a
 * vite.config.js. These assertions fail the moment that happens.
 */
class NoBuildToolchainTest extends TestCase
{
    private function basePath(string $path = ''): string
    {
        return dirname(__DIR__, 2) . ($path === '' ? '' : '/' . $path);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function forbiddenFiles(): array
    {
        $files = [
            'package.json',
            'package-lock.json',
            'yarn.lock',
            'pnpm-lock.yaml',
            'bun.lockb',
            'vite.config.js',
            'vite.config.ts',
            'webpack.mix.js',
            'postcss.config.js',
            'postcss.config.mjs',
            'tailwind.config.js',
            'tailwind.config.ts',
            '.nvmrc',
            '.npmrc',
        ];

        return array_combine($files, array_map(static fn (string $f): array => [$f], $files));
    }

    #[DataProvider('forbiddenFiles')]
    public function test_the_project_root_carries_no_build_tool_config(string $file): void
    {
        $this->assertFileDoesNotExist(
            $this->basePath($file),
            "{$file} projeye geri girmiş — build tool kullanılmıyor",
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function forbiddenDirectories(): array
    {
        return [
            'node_modules'  => ['node_modules'],
            'public/build'  => ['public/build'],
            'resources/js'  => ['resources/js'],
            'resources/css' => ['resources/css'],
        ];
    }

    #[DataProvider('forbiddenDirectories')]
    public function test_no_bundler_directory_exists(string $directory): void
    {
        $this->assertDirectoryDoesNotExist(
            $this->basePath($directory),
            "{$directory} dizini oluşmuş — varlıklar public/ altında elle yönetiliyor",
        );
    }

    /**
     * public/hot is written by the Vite dev server; if it is there, someone ran it.
     */
    public function test_the_vite_dev_server_marker_is_absent(): void
    {
        $this->assertFileDoesNotExist($this->basePath('public/hot'));
    }

    /**
     * @return array<int, string>
     */
    private function projectViews(): array
    {
        $paths = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            // admin-theme holds untouched reference HTML from the theme vendor;
            // it is never rendered, only read while converting a page to Blade.
            if (str_contains($file->getPathname(), 'admin-theme')) {
                continue;
            }

            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $paths[] = $file->getPathname();
            }
        }

        return $paths;
    }

    public function test_no_view_pulls_assets_through_vite(): void
    {
        $offenders = [];

        foreach ($this->projectViews() as $path) {
            if (preg_match('/@vite\b|@viteReactRefresh\b|Vite::/', (string) file_get_contents($path)) === 1) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame([], $offenders, 'Vite direktifi kullanan view: ' . implode(', ', $offenders));
    }

    /**
     * The Laravel Mix helper, without tripping over CSS color-mix().
     */
    public function test_no_view_uses_the_laravel_mix_helper(): void
    {
        $offenders = [];

        foreach ($this->projectViews() as $path) {
            if (preg_match('/(?<!color-)\bmix\s*\(/', (string) file_get_contents($path)) === 1) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame([], $offenders, 'mix() kullanan view: ' . implode(', ', $offenders));
    }

    public function test_composer_requires_no_node_based_tooling(): void
    {
        /** @var array<string, mixed> $composer */
        $composer = json_decode((string) file_get_contents($this->basePath('composer.json')), true);

        $packages = array_keys(
            ($composer['require'] ?? []) + ($composer['require-dev'] ?? []),
        );

        // Sail's container installs Node and its wrapper shells out to npm, so
        // it belongs to the toolchain this project does without.
        $this->assertNotContains('laravel/sail', $packages);
    }

    /**
     * The replacement for a bundler hash: the file's own mtime.
     */
    public function test_asset_versioning_comes_from_the_file_itself(): void
    {
        $url = versioned_asset('css/app.css');

        $this->assertStringEndsWith('?v=' . filemtime(public_path('css/app.css')), $url);
    }

    /**
     * Vendor libraries are committed files, not an install step.
     */
    public function test_vendor_front_end_libraries_are_committed(): void
    {
        $this->assertDirectoryExists($this->basePath('public/assets/vendor'));
        $this->assertFileExists($this->basePath('public/assets/vendor/bootstrap/bootstrap.min.css'));
        $this->assertFileExists($this->basePath('public/assets/vendor/bootstrap/bootstrap.bundle.min.js'));
    }
}
