<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Translation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Interface texts: the shipped defaults live in lang/{locale}/{group}.php, the
 * admin's edits live in the translations table.
 *
 * Reads go through DatabaseOverrideLoader, which asks this service for the
 * overrides of one group. That answer is cached forever and dropped on save, so
 * a page render costs one cache read rather than a query.
 */
final class TranslationService
{
    /**
     * Groups the panel lets an admin edit.
     *
     * validation.php and the framework's own files are deliberately left out:
     * they carry placeholders and pluralisation rules that a text box would
     * quietly break.
     *
     * @var array<int, string>
     */
    public const EDITABLE_GROUPS = ['site'];

    private const CACHE_PREFIX = 'translations.overrides';

    /**
     * Per-request memo, so several groups in one render do not each hit the
     * cache driver again.
     *
     * @var array<string, array<string, string>>
     */
    private array $memo = [];

    /**
     * Overridden lines for one group, as a flat "nav.home" => "Anasayfa" map.
     *
     * @return array<string, string>
     */
    public function overridesFor(string $locale, string $group): array
    {
        $memoKey = $locale . '|' . $group;

        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        return $this->memo[$memoKey] = Cache::rememberForever(
            $this->cacheKey($locale, $group),
            function () use ($locale, $group): array {
                // The loader runs on every request, including before the table
                // exists (a fresh clone, mid-migration). It must never be the
                // thing that breaks the site.
                //
                // Deliberately no Schema::hasTable() guard: that is a second
                // query on every cold load, forever, to protect against a state
                // that only exists before the first migration. The catch covers
                // it for free.
                try {
                    return Translation::query()
                        ->for($locale, $group)
                        ->pluck('value', 'key')
                        ->all();
                } catch (Throwable) {
                    return [];
                }
            },
        );
    }

    /**
     * The values the panel shows: file default overlaid with any override.
     *
     * @return array<string, string> flat "nav.home" => "Anasayfa"
     */
    public function effectiveLines(string $locale, string $group): array
    {
        return array_replace(
            $this->fileLines($locale, $group),
            $this->overridesFor($locale, $group),
        );
    }

    /**
     * The shipped defaults straight from the file, ignoring any override.
     *
     * @return array<string, string>
     */
    public function fileLines(string $locale, string $group): array
    {
        $path = lang_path("{$locale}/{$group}.php");

        if (! File::exists($path)) {
            return [];
        }

        $lines = require $path;

        return is_array($lines) ? Arr::dot($lines) : [];
    }

    /**
     * Every key the group defines, taken from the default language.
     *
     * The key list comes from the file rather than the database, so a string
     * added in code shows up in the panel by itself.
     *
     * @return array<string, string> key => default-language value
     */
    public function keysFrom(string $group, ?string $defaultLocale = null): array
    {
        $defaultLocale ??= app(LanguageService::class)->defaultCode();

        $lines = $this->fileLines($defaultLocale, $group);

        // A language added before its files exist would otherwise show nothing.
        if ($lines === []) {
            foreach ([config('app.fallback_locale'), 'tr', 'en'] as $fallback) {
                if (is_string($fallback) && ($lines = $this->fileLines($fallback, $group)) !== []) {
                    break;
                }
            }
        }

        return $lines;
    }

    /**
     * Store what the admin changed.
     *
     * A value equal to the file default is not stored as an override — it is
     * deleted instead, so "same as shipped" stays literally the same row-free
     * state and a later change to the default still reaches the site.
     *
     * @param array<string, string|null> $values flat "nav.home" => "Anasayfa"
     * @return array{saved: int, reset: int}
     */
    public function save(string $locale, string $group, array $values): array
    {
        $defaults = $this->fileLines($locale, $group);
        $allowed = array_keys($this->keysFrom($group));
        // What was already overridden, so "reset" counts strings the admin
        // actually put back — not the hundreds of untouched fields that happen
        // to equal the shipped default on every save.
        $existing = $this->overridesFor($locale, $group);

        $saved = 0;
        $reset = 0;
        $now = now();
        $upserts = [];
        $removals = [];

        foreach ($values as $key => $value) {
            // Only keys the group actually defines; a hand-crafted POST cannot
            // invent rows.
            if (! in_array($key, $allowed, true)) {
                continue;
            }

            $value = is_string($value) ? trim($value) : '';
            $default = (string) ($defaults[$key] ?? '');

            if ($value === '' || $value === $default) {
                $removals[] = $key;

                if (array_key_exists($key, $existing)) {
                    $reset++;
                }

                continue;
            }

            // Unchanged from what is already stored: nothing to write.
            if (($existing[$key] ?? null) === $value) {
                continue;
            }

            $upserts[] = [
                'locale'     => $locale,
                'group'      => $group,
                'key'        => $key,
                'value'      => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $saved++;
        }

        DB::transaction(function () use ($locale, $group, $upserts, $removals): void {
            if ($removals !== []) {
                Translation::withTrashed()
                    ->for($locale, $group)
                    ->whereIn('key', $removals)
                    ->forceDelete();
            }

            foreach (array_chunk($upserts, 200) as $chunk) {
                Translation::upsert($chunk, ['locale', 'group', 'key'], ['value', 'updated_at']);
            }
        });

        $this->forget($locale, $group);

        return ['saved' => $saved, 'reset' => $reset];
    }

    /**
     * Drop every override for a language, sending it back to the shipped file.
     */
    public function resetGroup(string $locale, string $group): int
    {
        $count = Translation::withTrashed()->for($locale, $group)->forceDelete();

        $this->forget($locale, $group);

        return (int) $count;
    }

    public function resetKey(string $locale, string $group, string $key): void
    {
        Translation::withTrashed()->for($locale, $group)->where('key', $key)->forceDelete();

        $this->forget($locale, $group);
    }

    /**
     * How many strings each language has overridden, for the screen's counters.
     *
     * @return array<string, int>
     */
    public function overrideCounts(string $group): array
    {
        return Translation::query()
            ->where('group', $group)
            ->selectRaw('locale, COUNT(*) as total')
            ->groupBy('locale')
            ->pluck('total', 'locale')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }

    public function forget(string $locale, string $group): void
    {
        Cache::forget($this->cacheKey($locale, $group));
        unset($this->memo[$locale . '|' . $group]);

        // The translator caches loaded groups for the life of the request.
        app('translator')->setLoaded([]);
    }

    public function flush(): void
    {
        foreach (app(LanguageService::class)->all() as $language) {
            foreach (self::EDITABLE_GROUPS as $group) {
                $this->forget($language->code, $group);
            }
        }
    }

    private function cacheKey(string $locale, string $group): string
    {
        return self::CACHE_PREFIX . ".{$locale}.{$group}";
    }

    /**
     * Çeviri anahtarlarını ekrandaki bölümlere ayırır.
     *
     * Hem çeviri ekranı hem dışa aktarma aynı gruplamayı okur: dosyaya inen
     * bölüm adı ekranda görünenle aynı olmalı.
     *
     * @param array<string, string> $keys
     * @param array<string, string> $current
     * @param array<string, string> $defaults
     * @param array<string, string> $overrides
     * @return array<string, array{label: string, icon: string, rows: list<array<string, mixed>>}>
     */
    public function groupIntoSections(array $keys, array $current, array $defaults, array $overrides): array
    {
        $sections = [];

        foreach ($keys as $key => $defaultLanguageValue) {
            $section = str_contains($key, '.') ? strtok($key, '.') : 'misc';

            $sections[$section]['label'] = $this->sectionLabel($section);
            $sections[$section]['icon'] = $this->sectionIcon($section);
            $sections[$section]['rows'][] = [
                'key'         => $key,
                'label'       => $this->keyLabel($key),
                'value'       => $current[$key] ?? '',
                'default'     => $defaults[$key] ?? '',
                'reference'   => $defaultLanguageValue,
                'overridden'  => array_key_exists($key, $overrides),
                'missing'     => ! array_key_exists($key, $defaults) && ! array_key_exists($key, $overrides),
                'multiline'   => mb_strlen((string) ($current[$key] ?? '')) > 90,
            ];
        }

        return $sections;
    }

    /**
     * Human name for a section, falling back to the raw key so a section added
     * in code still renders.
     */
    private function sectionLabel(string $section): string
    {
        return [
            'nav'        => 'Menü ve Navigasyon',
            'auth'       => 'Giriş / Üyelik Bağlantıları',
            'actions'    => 'Butonlar ve Aksiyonlar',
            'blog'       => 'Blog / İçerikler',
            'gallery'    => 'Galeri',
            'faq'        => 'Sıkça Sorulan Sorular',
            'contact'    => 'İletişim',
            'account'    => 'Hesabım',
            'login'      => 'Giriş Sayfası',
            'register'   => 'Kayıt Sayfası',
            'password'   => 'Şifre Sıfırlama',
            'verify'     => 'E-posta Doğrulama',
            'errors'     => 'Hata Sayfaları',
            'newsletter' => 'Bülten',
            'home'       => 'Anasayfa',
            'misc'       => 'Diğer',
        ][$section] ?? ucfirst($section);
    }

    private function sectionIcon(string $section): string
    {
        return [
            'nav'        => 'bi-list',
            'auth'       => 'bi-person-badge',
            'actions'    => 'bi-hand-index-thumb',
            'blog'       => 'bi-newspaper',
            'gallery'    => 'bi-images',
            'faq'        => 'bi-question-circle',
            'contact'    => 'bi-envelope',
            'account'    => 'bi-person-circle',
            'login'      => 'bi-box-arrow-in-right',
            'register'   => 'bi-person-plus',
            'password'   => 'bi-key',
            'verify'     => 'bi-shield-check',
            'errors'     => 'bi-exclamation-triangle',
            'newsletter' => 'bi-envelope-heart',
            'home'       => 'bi-house',
            'misc'       => 'bi-three-dots',
        ][$section] ?? 'bi-dot';
    }

    /**
     * Turn "comment_email_note" into "Comment email note" so the field has a
     * readable label without a hand-maintained list of every key.
     */
    private function keyLabel(string $key): string
    {
        $last = str_contains($key, '.') ? substr($key, strrpos($key, '.') + 1) : $key;

        return ucfirst(str_replace('_', ' ', $last));
    }
}
