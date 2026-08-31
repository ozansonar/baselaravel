<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Str;

trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function ($model): void {
            if (empty($model->slug)) {
                $model->slug = static::generateUniqueSlug($model->{static::slugSource()}, null, static::slugLocale($model));
            }
        });

        static::updating(function ($model): void {
            $source = static::slugSource();
            if ($model->isDirty($source) && ! $model->isDirty('slug')) {
                $model->slug = static::generateUniqueSlug($model->{$source}, $model->id, static::slugLocale($model));
            }
        });
    }

    /**
     * Language a slug has to be unique within, or null when the model is not
     * translated.
     *
     * Resolved here rather than read straight off the model because HasSlug's
     * creating listener can run before HasTranslations has filled in the
     * locale.
     */
    protected static function slugLocale($model): ?string
    {
        if (! in_array(HasTranslations::class, class_uses_recursive($model), true)) {
            return null;
        }

        return $model->locale ?: app(\App\Services\LanguageService::class)->defaultCode();
    }

    /**
     * Column name used as the source for slug generation.
     */
    protected static function slugSource(): string
    {
        return 'name';
    }

    /**
     * Generate a unique slug for the model.
     */
    protected static function generateUniqueSlug(string $value, ?int $ignoreId = null, ?string $locale = null): string
    {
        $slug = Str::slug($value);
        $original = $slug;
        $counter = 1;

        while (static::slugTaken($slug, $ignoreId, $locale)) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Slugs only have to be unique within a language, so Turkish and English
     * may both use "iletisim" / "contact" style slugs freely.
     */
    protected static function slugTaken(string $slug, ?int $ignoreId, ?string $locale): bool
    {
        $query = static::withTrashed()->where('slug', $slug);

        if ($locale !== null) {
            $query->where('locale', $locale);
        }

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
