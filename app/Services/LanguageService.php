<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Language;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

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
     * İstek içi hatırlatıcı.
     *
     * Cache::remember her çağrıda önbellek sürücüsüne gidiyor; sürücü
     * veritabanıysa (bu projenin varsayılanı) her çağrı bir SELECT demek.
     * Dil listesi bir istek boyunca onlarca kez soruluyor — locale kapsamı,
     * hreflang, dil değiştirici, çeviri bağlantıları — ve tek bir sayfada
     * yirmiden fazla "select * from cache" doğuruyordu. Cevap istek içinde
     * değişmediği için ilk okumada burada saklanıyor.
     */
    private ?Collection $activeMemo = null;

    private ?Language $defaultMemo = null;

    private bool $defaultResolved = false;

    /**
     * @return Collection<int, Language>
     */
    public function active(): Collection
    {
        if ($this->activeMemo !== null) {
            return $this->activeMemo;
        }

        /** @var Collection<int, Language> $languages */
        $languages = Cache::remember(
            self::CACHE_KEY_ACTIVE,
            self::CACHE_TTL,
            fn () => Language::active()->sorted()->get(),
        );

        return $this->activeMemo = $languages;
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
        if ($this->defaultResolved) {
            return $this->defaultMemo;
        }

        /** @var Language|null $language */
        $language = Cache::remember(
            self::CACHE_KEY_DEFAULT,
            self::CACHE_TTL,
            fn () => Language::active()->where('is_default', true)->first()
                ?? Language::active()->sorted()->first(),
        );

        $this->defaultResolved = true;

        return $this->defaultMemo = $language;
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

    /**
     * Liste ekranının tanıdığı süzgeç anahtarları.
     *
     * Ekran da dışa aktarma da bu listeyi okur; iki yerde ayrı yazılsaydı
     * dosyaya inen ile ekranda görünen zamanla ayrışırdı.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return ['search', 'status', 'files', 'content', 'sort'];
    }

    /**
     * Arayüz çevirisi dosyası bulunan dil kodları.
     *
     * Sütun değil dosya sistemi gerçeği: lang/ altında klasörü olan diller.
     *
     * @return list<string>
     */
    public function translatedLocales(): array
    {
        if (! File::isDirectory(lang_path())) {
            return [];
        }

        return collect(File::directories(lang_path()))
            ->map(static fn (string $path): string => basename($path))
            ->values()
            ->all();
    }

    /**
     * Dil başına içerik adedi.
     *
     * Bir dil, arkasında ne kadar içerik olduğu bilinmeden kapatılmasın ya da
     * silinmesin diye dokuz tablodan toplanıyor.
     *
     * @return array<string, int>
     */
    public function contentCounts(): array
    {
        $tables = [
            'pages', 'blog_posts', 'blog_categories', 'gallery_categories',
            'gallery_items', 'faqs', 'sliders', 'popups', 'menus',
        ];

        $counts = [];

        foreach ($tables as $table) {
            foreach (
                DB::table($table)
                    ->selectRaw('locale, COUNT(*) as total')
                    ->whereNull('deleted_at')
                    ->groupBy('locale')
                    ->get() as $row
            ) {
                $locale = (string) $row->locale;
                $counts[$locale] = ($counts[$locale] ?? 0) + (int) $row->total;
            }
        }

        return $counts;
    }

    /**
     * Süzgeçler uygulanmış, sayfalanmamış dil sorgusu.
     *
     * @param array<string, mixed> $filters
     * @return Builder<Language>
     */
    public function query(array $filters = []): Builder
    {
        $query = Language::query();

        if (($filters['search'] ?? '') !== '') {
            // Joker karakterler düz metin sayılıyor: "%" yazan biri tüm listeyi
            // getirmemeli.
            $term = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';

            $query->where(function (Builder $sub) use ($term): void {
                $sub->where('name', 'like', $term)
                    ->orWhere('native_name', 'like', $term)
                    ->orWhere('code', 'like', $term);
            });
        }

        match ($filters['status'] ?? '') {
            'active'   => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            'default'  => $query->where('is_default', true),
            default    => null,
        };

        // Arayüz çevirisi bir dosya sistemi gerçeği: süzgeç, diskte bulunan
        // kodlar üzerinden çalışıyor.
        $translated = $this->translatedLocales();

        match ($filters['files'] ?? '') {
            'yes'   => $query->whereIn('code', $translated ?: ['']),
            'no'    => $query->whereNotIn('code', $translated ?: ['']),
            default => null,
        };

        // İçerik sayısı da sütun değil: dokuz tablodan toplanıyor.
        $withContent = array_keys(array_filter($this->contentCounts(), static fn (int $count): bool => $count > 0));

        match ($filters['content'] ?? '') {
            'yes'   => $query->whereIn('code', $withContent ?: ['']),
            'no'    => $query->whereNotIn('code', $withContent ?: ['']),
            default => null,
        };

        match ($filters['sort'] ?? '') {
            'name'   => $query->orderBy('name'),
            'code'   => $query->orderBy('code'),
            'recent' => $query->latest('id'),
            'oldest' => $query->oldest('id'),
            // Varsayılan dil hep başta: listenin çıpası o.
            default  => $query->orderByDesc('is_default')->orderBy('sort_order')->orderBy('name'),
        };

        return $query;
    }

    public function clearCache(): void
    {
        // İstek içi hatırlatıcı da düşüyor: aynı istekte dil eklenip hemen
        // liste sorulduğunda (panelde olan budur) eski liste dönerdi.
        $this->activeMemo = null;
        $this->defaultMemo = null;
        $this->defaultResolved = false;

        Cache::forget(self::CACHE_KEY_ACTIVE);
        Cache::forget(self::CACHE_KEY_DEFAULT);
        // Switching a language on or off adds or removes a whole language's
        // URLs, so the sitemap is stale the moment this changes.
        Cache::forget('sitemap.urls');
    }
}
