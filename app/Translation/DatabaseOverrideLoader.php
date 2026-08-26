<?php

declare(strict_types=1);

namespace App\Translation;

use App\Services\TranslationService;
use Illuminate\Contracts\Translation\Loader;

/**
 * Wraps Laravel's file loader and lays the admin's edits over the result.
 *
 * The file is still the source of every key; the database only carries what
 * someone changed in the panel. Laravel loads a group once per request, so this
 * costs one cached array lookup per group — not one per translated string.
 */
final class DatabaseOverrideLoader implements Loader
{
    public function __construct(
        private readonly Loader $files,
        private readonly TranslationService $translations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function load($locale, $group, $namespace = null): array
    {
        $lines = $this->files->load($locale, $group, $namespace);

        // Package translations (vendor namespaces) are not editable from the
        // panel, and neither are the JSON strings Laravel loads under '*'.
        if (($namespace !== null && $namespace !== '*') || $group === '*') {
            return $lines;
        }

        $overrides = $this->translations->overridesFor((string) $locale, (string) $group);

        if ($overrides === []) {
            return $lines;
        }

        foreach ($overrides as $key => $value) {
            data_set($lines, $key, $value);
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->files->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->files->addJsonPath($path);
    }

    /**
     * @return array<string, string>
     */
    public function namespaces(): array
    {
        return $this->files->namespaces();
    }
}
