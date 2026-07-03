<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CityLandingPage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CityLandingPageService
{
    private const string CACHE_TAG_ACTIVE = 'city_landing_pages.active';

    public function __construct(
        private readonly ?AiCityContentService $aiCityContentService = null,
    ) {}

    // ── Frontend ──

    /**
     * @return Collection<int, CityLandingPage>
     */
    public function activePages(): Collection
    {
        return Cache::remember(self::CACHE_TAG_ACTIVE, 1800, function (): Collection {
            return CityLandingPage::active()->sorted()->get();
        });
    }

    public function findActiveBySlug(string $slug): ?CityLandingPage
    {
        return CityLandingPage::active()->where('city_slug', $slug)->first();
    }

    // ── Admin ──

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 25, array $filters = []): LengthAwarePaginator
    {
        $query = CityLandingPage::query()->orderBy('tier')->orderBy('sort_order')->orderBy('city_name');

        if (isset($filters['status'])) {
            match ($filters['status']) {
                'active'   => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                'trashed'  => $query->onlyTrashed(),
                default    => null,
            };
        }

        if (! empty($filters['tier'])) {
            $query->where('tier', (int) $filters['tier']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('city_name', 'like', "%{$search}%")
                  ->orWhere('city_slug', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * @return array{total: int, active: int, inactive: int, trashed: int}
     */
    public function getAdminStats(): array
    {
        return Cache::remember('city_landing_pages.admin_stats', 300, function (): array {
            $counts = CityLandingPage::withTrashed()->selectRaw("
                SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN deleted_at IS NULL AND is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN deleted_at IS NULL AND is_active = 0 THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as trashed
            ")->first();

            return [
                'total'    => (int) ($counts->total ?? 0),
                'active'   => (int) ($counts->active ?? 0),
                'inactive' => (int) ($counts->inactive ?? 0),
                'trashed'  => (int) ($counts->trashed ?? 0),
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function getStatusCounts(): array
    {
        $stats = $this->getAdminStats();

        return [
            'all'      => $stats['total'],
            'active'   => $stats['active'],
            'inactive' => $stats['inactive'],
            'trashed'  => $stats['trashed'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CityLandingPage
    {
        return DB::transaction(function () use ($data): CityLandingPage {
            $data['city_slug'] = $this->generateUniqueSlug(
                (string) ($data['city_slug'] ?? $data['city_name']),
            );

            $page = CityLandingPage::create($data);
            $this->clearCache();

            return $page;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CityLandingPage $page, array $data): CityLandingPage
    {
        return DB::transaction(function () use ($page, $data): CityLandingPage {
            if (isset($data['city_slug']) || isset($data['city_name'])) {
                $candidate = (string) ($data['city_slug'] ?? $data['city_name'] ?? $page->city_name);
                $data['city_slug'] = $this->generateUniqueSlug($candidate, $page->id);
            }

            $page->update($data);
            $this->clearCache();

            return $page->refresh();
        });
    }

    /**
     * Bulk create city pages from a flat list — slug auto-generated.
     *
     * When `$fillWithAi` is true, each freshly created page is also enriched
     * via Gemini synchronously after the transaction commits. This is a heavy
     * operation (~30-60s per city) — caller must extend `set_time_limit`.
     * Failures are silent: the city remains in the DB with the default
     * skeleton content and the user can re-run AI later from the edit page.
     *
     * @param  list<array{city_name: string, region?: ?string, tier?: int}>  $cities
     * @return array{created: int, skipped: int, ai_filled: int, ai_failed: int}
     */
    public function bulkCreate(array $cities, bool $fillWithAi = false): array
    {
        $created   = 0;
        $skipped   = 0;
        $aiFilled  = 0;
        $aiFailed  = 0;
        $newPages  = [];

        DB::transaction(function () use ($cities, &$created, &$skipped, &$newPages): void {
            foreach ($cities as $city) {
                $name = trim((string) ($city['city_name'] ?? ''));
                if ($name === '') {
                    $skipped++;
                    continue;
                }

                // Skip if a city with the same canonical slug (or matching name)
                // already exists — even when soft deleted. We do NOT want bulk
                // creation to silently produce "istanbul-1" duplicates.
                $candidateSlug = Str::slug($name);
                $alreadyExists = CityLandingPage::withTrashed()
                    ->where(function ($q) use ($candidateSlug, $name): void {
                        $q->where('city_slug', $candidateSlug)
                          ->orWhere('city_name', $name);
                    })
                    ->exists();

                if ($alreadyExists) {
                    $skipped++;
                    continue;
                }

                $slug = $this->generateUniqueSlug($name);

                $page = CityLandingPage::create([
                    'city_name'        => $name,
                    'city_slug'        => $slug,
                    'region'           => $city['region'] ?? null,
                    'tier'             => (int) ($city['tier'] ?? 1),
                    'title'            => "{$name} Köy Ürünleri — Doğal Süt, Peynir, Tereyağı",
                    'meta_title'       => "{$name} Köy Ürünleri | Orhan Babanın Çiftliği",
                    'meta_description' => "Çorum köyünden {$name}'a soğuk zincir kargo ile doğal köy ürünleri. Taze süt, köy peyniri, tereyağı. {$name}'a kapınıza teslimat.",
                    'hero_heading'     => "{$name}'a Doğal Köy Ürünleri — Çorum'dan Kapınıza",
                    'hero_description' => "Çorum Büyük Palabıyık Köyü'nden {$name} adresinize soğuk zincir kargo ile doğal köy ürünleri.",
                    'content'          => null,
                    'shipping_note'    => "Çorum'dan {$name}'a soğuk zincir kargo ile 1-2 iş günü içinde teslimat.",
                    'delivery_time'    => '1-2 iş günü',
                    'is_active'        => true,
                    'sort_order'       => 0,
                ]);

                $newPages[] = $page;
                $created++;
            }

            $this->clearCache();
        });

        // AI enrichment is intentionally OUTSIDE the transaction — Gemini
        // calls take 30-60s each and we don't want to hold a DB transaction
        // open for minutes. Each city is independent; failure on one does not
        // affect others.
        if ($fillWithAi && $this->aiCityContentService !== null && $newPages !== []) {
            foreach ($newPages as $page) {
                $result = $this->aiCityContentService->generate(
                    $page->city_name,
                    $page->region,
                );

                if ($result['success'] && ! empty($result['data'])) {
                    $this->update($page, $result['data']);
                    $aiFilled++;
                } else {
                    $aiFailed++;
                }
            }
        }

        return [
            'created'   => $created,
            'skipped'   => $skipped,
            'ai_filled' => $aiFilled,
            'ai_failed' => $aiFailed,
        ];
    }

    public function delete(CityLandingPage $page): void
    {
        DB::transaction(function () use ($page): void {
            $page->delete();
            $this->clearCache();
        });
    }

    public function restore(int $id): CityLandingPage
    {
        $page = CityLandingPage::withTrashed()->findOrFail($id);
        $page->restore();
        $this->clearCache();

        return $page;
    }

    /**
     * Generate unique city_slug from a free-form value.
     * Uniqueness is checked across soft-deleted rows too (slug column is unique).
     */
    public function generateUniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $slug = Str::slug($value);
        if ($slug === '') {
            $slug = 'sehir';
        }

        $original = $slug;
        $counter = 1;

        $exists = function (string $candidate) use ($ignoreId): bool {
            $q = CityLandingPage::withTrashed()->where('city_slug', $candidate);
            if ($ignoreId !== null) {
                $q->where('id', '!=', $ignoreId);
            }

            return $q->exists();
        };

        while ($exists($slug)) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_TAG_ACTIVE);
        Cache::forget('city_landing_pages.admin_stats');
        Cache::forget('sitemap.urls');
        Cache::forget('sitemap_page.groups');
    }
}
