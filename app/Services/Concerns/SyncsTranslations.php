<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Models\Language;
use App\Services\LanguageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saves a form that carries one block of fields per language.
 *
 * The admin forms post translations[{locale}][field], and every language block
 * becomes its own row sharing a lang_group_id. Because a translation is a whole
 * row, per-language files (an image with text baked into it) come along for
 * free — each block brings its own upload.
 *
 * A language block that is left empty is skipped rather than saved blank, so
 * content can be translated later without creating placeholder rows.
 */
trait SyncsTranslations
{
    /**
     * @param class-string<Model> $modelClass
     * @param array<string, array<string, mixed>> $translations locale => fields
     * @param callable(array<string, mixed>, string, Model|null): array<string, mixed> $prepare
     *        Hook for per-row work such as uploads; receives the fields, the
     *        locale and the existing row (null when creating).
     */
    protected function saveTranslations(
        string $modelClass,
        array $translations,
        callable $prepare,
        ?string $groupId = null,
    ): string {
        $groupId ??= (string) Str::uuid();

        DB::transaction(function () use ($modelClass, $translations, $prepare, $groupId): void {
            foreach ($this->activeLocales() as $locale) {
                $fields = $translations[$locale] ?? null;

                /** @var Model|null $existing */
                $existing = $modelClass::query()
                    ->where('lang_group_id', $groupId)
                    ->where('locale', $locale)
                    ->first();

                if ($fields === null || $this->isEmptyBlock($fields)) {
                    // Nothing supplied: leave an existing translation untouched
                    // rather than wiping it, and do not create an empty one.
                    continue;
                }

                $payload = $prepare($fields, $locale, $existing);

                if ($payload === []) {
                    continue;
                }

                if ($existing !== null) {
                    $existing->update($payload);

                    continue;
                }

                $modelClass::create($payload + [
                    'locale'        => $locale,
                    'lang_group_id' => $groupId,
                ]);
            }
        });

        return $groupId;
    }

    /**
     * @return array<int, string>
     */
    protected function activeLocales(): array
    {
        return app(LanguageService::class)->activeCodes();
    }

    protected function defaultLocale(): string
    {
        return app(LanguageService::class)->defaultCode();
    }

    /**
     * A block counts as empty when the translator filled in nothing at all;
     * checkboxes and hidden defaults must not make it look filled.
     *
     * @param array<string, mixed> $fields
     */
    protected function isEmptyBlock(array $fields): bool
    {
        foreach ($fields as $key => $value) {
            if (in_array($key, ['locale', 'lang_group_id', 'id'], true)) {
                continue;
            }

            if (is_array($value)) {
                if ($value !== []) {
                    return false;
                }

                continue;
            }

            if ($value instanceof \Illuminate\Http\UploadedFile) {
                return false;
            }

            if (is_string($value) && trim($value) !== '') {
                return false;
            }

            if (! is_string($value) && $value !== null && $value !== false && $value !== '0') {
                return false;
            }
        }

        return true;
    }

    /**
     * Languages the form should render a tab for, default language first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Language>
     */
    public function formLanguages(): \Illuminate\Database\Eloquent\Collection
    {
        $languages = app(LanguageService::class)->active();

        return $languages->sortByDesc(fn (Language $language): bool => $language->is_default)->values();
    }
}
