<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Language;
use App\Services\LanguageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * One row per language, translations of the same content sharing a
 * lang_group_id.
 *
 * Because the translation is a whole row, every column is per language — the
 * image included, which is what you want when the artwork carries text.
 *
 * @property string $locale
 * @property string|null $lang_group_id
 */
trait HasTranslations
{
    public static function bootHasTranslations(): void
    {
        static::creating(function ($model): void {
            if (empty($model->locale)) {
                $model->locale = app(LanguageService::class)->defaultCode();
            }

            // A row saved without a group is the first version of new content.
            if (empty($model->lang_group_id)) {
                $model->lang_group_id = (string) Str::uuid();
            }
        });
    }

    /**
     * Rows for the same content in every language, this one included.
     *
     * @return Builder<static>
     */
    public function translationsQuery(): Builder
    {
        return static::query()->where('lang_group_id', $this->lang_group_id);
    }

    /**
     * @return Collection<int, static>
     */
    public function translations(): Collection
    {
        /** @var Collection<int, static> $rows */
        $rows = $this->translationsQuery()->get();

        return $rows;
    }

    /**
     * @return Collection<int, static>
     */
    public function siblingTranslations(): Collection
    {
        /** @var Collection<int, static> $rows */
        $rows = $this->translationsQuery()->where('id', '!=', $this->getKey())->get();

        return $rows;
    }

    public function translation(string $locale): ?static
    {
        /** @var static|null $row */
        $row = $this->translationsQuery()->where('locale', $locale)->first();

        return $row;
    }

    public function hasTranslation(string $locale): bool
    {
        return $this->translationsQuery()->where('locale', $locale)->exists();
    }

    /**
     * Locales this content already exists in.
     *
     * @return array<int, string>
     */
    public function translatedLocales(): array
    {
        return $this->translationsQuery()->pluck('locale')->all();
    }

    /**
     * Languages that still need a translation of this content.
     *
     * @return Collection<int, Language>
     */
    public function missingLanguages(): Collection
    {
        $existing = $this->translatedLocales();

        return app(LanguageService::class)
            ->active()
            ->reject(fn (Language $language): bool => in_array($language->code, $existing, true))
            ->values();
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeLocale(Builder $query, ?string $locale = null): Builder
    {
        return $query->where('locale', $locale ?? app()->getLocale());
    }

    /**
     * Rows in the requested language, falling back to the default language for
     * content that has not been translated yet.
     *
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeLocaleWithFallback(Builder $query, ?string $locale = null): Builder
    {
        $languages = app(LanguageService::class);
        $locale ??= app()->getLocale();
        $fallback = $languages->defaultCode();

        if ($locale === $fallback) {
            return $query->where('locale', $fallback);
        }

        $table = $this->getTable();

        return $query
            ->whereIn('locale', [$locale, $fallback])
            ->whereNotExists(function ($sub) use ($table, $locale): void {
                $sub->selectRaw('1')
                    ->from($table . ' as preferred')
                    ->whereColumn('preferred.lang_group_id', $table . '.lang_group_id')
                    ->where('preferred.locale', $locale)
                    ->whereColumn('preferred.id', '!=', $table . '.id')
                    ->whereNull('preferred.deleted_at');
            });
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeInGroup(Builder $query, string $groupId): Builder
    {
        return $query->where('lang_group_id', $groupId);
    }
}
