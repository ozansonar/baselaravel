<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Language;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Owns the language list and the "exactly one default" rule.
 *
 * Every content row belongs to a single language, so a language row is what the
 * translation tabs, the front-end switcher and the locale middleware all read.
 */
final class LanguageService
{
    private const CACHE_KEY_ACTIVE = 'languages.active';
    private const CACHE_KEY_DEFAULT = 'languages.default';
    private const CACHE_TTL = 86400;

    /**
     * @return Collection<int, Language>
     */
    public function active(): Collection
    {
        /** @var Collection<int, Language> $languages */
        $languages = Cache::remember(
            self::CACHE_KEY_ACTIVE,
            self::CACHE_TTL,
            fn () => Language::active()->sorted()->get(),
        );

        return $languages;
    }

    /**
     * @return Collection<int, Language>
     */
    public function all(): Collection
    {
        return Language::sorted()->get();
    }

    /**
     * The single default language. Falls back to the first active row so the
     * site keeps working even if the flag was lost.
     */
    public function default(): ?Language
    {
        /** @var Language|null $language */
        $language = Cache::remember(
            self::CACHE_KEY_DEFAULT,
            self::CACHE_TTL,
            fn () => Language::active()->where('is_default', true)->first()
                ?? Language::active()->sorted()->first(),
        );

        return $language;
    }

    public function defaultCode(): string
    {
        return $this->default()?->code ?? config('app.locale', 'tr');
    }

    /**
     * @return array<int, string>
     */
    public function activeCodes(): array
    {
        return $this->active()->pluck('code')->all();
    }

    public function isSupported(?string $code): bool
    {
        return $code !== null && in_array($code, $this->activeCodes(), true);
    }

    public function findByCode(string $code): ?Language
    {
        return $this->active()->firstWhere('code', $code);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Language
    {
        return DB::transaction(function () use ($data): Language {
            $language = Language::create([
                'code'        => strtolower(trim($data['code'])),
                'name'        => $data['name'],
                'native_name' => $data['native_name'] ?? null,
                'flag'        => $data['flag'] ?? null,
                'is_active'   => (bool) ($data['is_active'] ?? true),
                'is_default'  => false,
                'sort_order'  => (int) ($data['sort_order'] ?? 0),
            ]);

            // The very first language has to be the default, otherwise the site
            // would have none.
            if (Language::where('is_default', true)->doesntExist()) {
                $this->makeDefault($language);
            }

            $this->clearCache();

            return $language;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Language $language, array $data): Language
    {
        DB::transaction(function () use ($language, $data): void {
            $language->update([
                'code'        => strtolower(trim($data['code'] ?? $language->code)),
                'name'        => $data['name'],
                'native_name' => $data['native_name'] ?? null,
                'flag'        => $data['flag'] ?? null,
                'sort_order'  => (int) ($data['sort_order'] ?? $language->sort_order),
                // The default language can never be switched off; that would
                // leave the site without a fallback.
                'is_active'   => $language->is_default ? true : (bool) ($data['is_active'] ?? true),
            ]);

            $this->clearCache();
        });

        return $language->refresh();
    }

    /**
     * Move the default flag. Exactly one row keeps it.
     */
    public function makeDefault(Language $language): void
    {
        DB::transaction(function () use ($language): void {
            Language::where('id', '!=', $language->id)->update(['is_default' => false]);

            $language->forceFill([
                'is_default' => true,
                'is_active'  => true,
            ])->save();

            $this->clearCache();
        });
    }

    /**
     * @return array{deleted: bool, message: string}
     */
    public function delete(Language $language): array
    {
        if ($language->is_default) {
            return ['deleted' => false, 'message' => 'Varsayılan dil silinemez. Önce başka bir dili varsayılan yapın.'];
        }

        if (Language::count() <= 1) {
            return ['deleted' => false, 'message' => 'Son dil silinemez.'];
        }

        $language->delete();
        $this->clearCache();

        return ['deleted' => true, 'message' => 'Dil silindi.'];
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_ACTIVE);
        Cache::forget(self::CACHE_KEY_DEFAULT);
        // Switching a language on or off adds or removes a whole language's
        // URLs, so the sitemap is stale the moment this changes.
        Cache::forget('sitemap.urls');
    }
}
