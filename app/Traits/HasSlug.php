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
                $model->slug = static::generateUniqueSlug($model->{static::slugSource()});
            }
        });

        static::updating(function ($model): void {
            $source = static::slugSource();
            if ($model->isDirty($source) && !$model->isDirty('slug')) {
                $model->slug = static::generateUniqueSlug($model->{$source}, $model->id);
            }
        });
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
    protected static function generateUniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $slug = Str::slug($value);
        $original = $slug;
        $counter = 1;

        $query = static::withTrashed()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        while ($query->exists()) {
            $slug = "{$original}-{$counter}";
            $counter++;

            $query = static::withTrashed()->where('slug', $slug);

            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }
        }

        return $slug;
    }
}
