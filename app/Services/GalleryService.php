<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GalleryType;
use App\Models\GalleryItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

final class GalleryService
{
    use \App\Services\Concerns\LocalizedCache;

    use \App\Services\Concerns\ListsTranslationGroups;
    use \App\Services\Concerns\SyncsTranslations;

    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    /**
     * @return Collection<int, GalleryItem>
     */
    public function activePhotos(): Collection
    {
        return Cache::remember($this->localeCacheKey('gallery.photos'), 3600, fn () =>
            GalleryItem::active()->localeWithFallback()->photos()->sorted()->with('galleryCategory')->get(),
        );
    }

    /**
     * @return Collection<int, GalleryItem>
     */
    public function activeVideos(): Collection
    {
        return Cache::remember($this->localeCacheKey('gallery.videos'), 3600, fn () =>
            GalleryItem::active()->localeWithFallback()->videos()->sorted()->with('galleryCategory')->get(),
        );
    }

    /**
     * @return array<string, Collection<int, GalleryItem>>
     */
    public function allActiveGrouped(): array
    {
        return [
            'photos' => $this->activePhotos(),
            'videos' => $this->activeVideos(),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
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
        return ['status', 'type', 'category', 'search'];
    }

    /**
     * Süzgeçler uygulanmış, sayfalanmamış sorgu.
     *
     * @param array<string, mixed> $filters
     * @return Builder<GalleryItem>
     */
    public function query(array $filters = []): Builder
    {
        $query = $this->onlyGroupRepresentatives(GalleryItem::withTrashed(), GalleryItem::class)->sorted()->with('galleryCategory');

        if (!empty($filters['type'])) {
            $type = GalleryType::tryFrom($filters['type']);
            if ($type) {
                $query->where('type', $type);
            }
        }

        if (!empty($filters['category'])) {
            $categoryId = (int) $filters['category'];
            if ($categoryId > 0) {
                $query->where('gallery_category_id', $categoryId);
            }
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'trashed') {
                $query->onlyTrashed();
            } elseif ($filters['status'] === 'active') {
                $query->whereNull('deleted_at')->where('is_active', true);
            } elseif ($filters['status'] === 'passive') {
                $query->whereNull('deleted_at')->where('is_active', false);
            }
        } else {
            $query->whereNull('deleted_at');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $this->whereGroupMatches($query, GalleryItem::class, function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->attachGroupLocales($this->query($filters)->paginate($perPage), GalleryItem::class);
    }

    public function findById(int $id): GalleryItem
    {
        return GalleryItem::with('galleryCategory')->findOrFail($id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): GalleryItem
    {
        return DB::transaction(function () use ($data): GalleryItem {
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $data['image'] = $this->uploadService->uploadImage(
                    $data['image'],
                    'gallery',
                    $data['title'],
                    ['lg', 'md'],
                );
            }

            $item = GalleryItem::create($data);
            $this->clearCache();

            return $item;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(GalleryItem $item, array $data): GalleryItem
    {
        return DB::transaction(function () use ($item, $data): GalleryItem {
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $data['image'] = $this->uploadService->replaceImage(
                    $data['image'],
                    'gallery',
                    $data['title'] ?? $item->title,
                    $item->image,
                    ['lg', 'md'],
                );
            }

            $item->update($data);
            $this->clearCache();

            return $item->refresh();
        });
    }

    /**
     * @param array<string, array<string, mixed>> $translations locale => fields
     */
    public function createTranslated(array $translations): string
    {
        $groupId = $this->saveTranslations(
            GalleryItem::class,
            $translations,
            fn (array $fields, string $locale, ?GalleryItem $existing, ?GalleryItem $default): array =>
                $this->prepareImageField($fields, $existing, 'gallery', 'title', 'image', $default),
        );

        $this->clearCache();

        return $groupId;
    }

    /**
     * @param array<string, array<string, mixed>> $translations locale => fields
     */
    public function updateTranslated(GalleryItem $galleryItem, array $translations): string
    {
        $groupId = $this->saveTranslations(
            GalleryItem::class,
            $translations,
            fn (array $fields, string $locale, ?GalleryItem $existing, ?GalleryItem $default): array =>
                $this->prepareImageField($fields, $existing, 'gallery', 'title', 'image', $default),
            $galleryItem->lang_group_id,
        );

        $this->clearCache();

        return $groupId;
    }

    // ── Toplu yükleme ──

    /**
     * Bırakılan tek görseli galeri öğesine çevirir.
     *
     * Yüz fotoğraflık bir etkinlikte tekli formu yüz kez doldurmak mümkün değil;
     * dosyalar bırakılır bırakılmaz kaydediliyor ve başlıkları sonradan ızgarada
     * düzeltiliyor. Kayıt yüklemeyle birlikte doğuyor, "kaydet"i beklemiyor:
     * bekletilseydi tarayıcı kapandığında yüz yükleme çöpe giderdi.
     *
     * Başlık zorunlu bir alan ve dosya adından türetiliyor — "bahar-senligi-01.jpg"
     * → "Bahar Senligi 01". Boş kalamaz, kullanıcı da yüz başlığı elle yazmak
     * zorunda değil.
     *
     * @param array{locale: string, gallery_category_id: ?int, is_active: bool, sort_order: int} $shared
     */
    public function createFromUpload(\Illuminate\Http\UploadedFile $file, array $shared): GalleryItem
    {
        $title = $this->titleFromFilename($file->getClientOriginalName());

        return DB::transaction(function () use ($file, $shared, $title): GalleryItem {
            $item = GalleryItem::create([
                'locale'              => $shared['locale'],
                'title'               => $title,
                'type'                => GalleryType::Photo,
                'gallery_category_id' => $shared['gallery_category_id'],
                'is_active'           => $shared['is_active'],
                'sort_order'          => $shared['sort_order'],
                'image'               => $this->uploadService->uploadImage($file, 'gallery', $title, ['lg', 'md']),
            ]);

            $this->clearCache();

            return $item;
        });
    }

    /**
     * Izgarada düzeltilen başlıkları tek seferde yazar.
     *
     * @param  array<int, string> $titles id => başlık
     * @return int                        değişen kayıt sayısı
     */
    public function renameMany(array $titles): int
    {
        if ($titles === []) {
            return 0;
        }

        $degisen = 0;

        DB::transaction(function () use ($titles, &$degisen): void {
            $items = GalleryItem::whereIn('id', array_keys($titles))->get()->keyBy('id');

            foreach ($titles as $id => $title) {
                $item = $items->get($id);

                if ($item === null || $item->title === $title) {
                    continue;
                }

                $item->update(['title' => $title]);
                $degisen++;
            }
        });

        if ($degisen > 0) {
            $this->clearCache();
        }

        return $degisen;
    }

    /**
     * Dosya adını okunur bir başlığa çevirir.
     *
     * Uzantı atılıyor, ayraçlar boşluğa dönüyor, baş harfler büyütülüyor.
     * Adı boş kalan dosya (".jpg" gibi) başlıksız kalamaz; zorunlu alan.
     */
    private function titleFromFilename(string $filename): string
    {
        $ad = \Illuminate\Support\Str::of(pathinfo($filename, PATHINFO_FILENAME))
            ->replaceMatches('/[_\-]+/u', ' ')
            ->squish()
            ->limit(240, '')
            ->trim();

        if ($ad->isEmpty()) {
            return 'Görsel';
        }

        return \Illuminate\Support\Str::title((string) $ad);
    }

    public function delete(GalleryItem $item): void
    {
        $this->deleteTranslationGroup($item);
        $this->clearCache();
    }

    public function restore(int $id): GalleryItem
    {
        $item = GalleryItem::withTrashed()->findOrFail($id);

        $this->restoreTranslationGroup($item);
        $this->clearCache();

        return $item->refresh();
    }

    /**
     * @return array<string, int>
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.gallery.stats', 300, function (): array {
            $counts = $this->onlyGroupRepresentatives(GalleryItem::withTrashed(), GalleryItem::class)
                ->selectRaw('sum(case when deleted_at is null then 1 else 0 end) as total')
                ->selectRaw('sum(case when deleted_at is null and type = ? then 1 else 0 end) as photos', [GalleryType::Photo->value])
                ->selectRaw('sum(case when deleted_at is null and type = ? then 1 else 0 end) as videos', [GalleryType::Video->value])
                ->selectRaw('sum(case when deleted_at is null and is_active = 1 then 1 else 0 end) as active')
                ->selectRaw('sum(case when deleted_at is not null then 1 else 0 end) as trashed')
                ->first();

            return [
                'total'   => (int) $counts->total,
                'photos'  => (int) $counts->photos,
                'videos'  => (int) $counts->videos,
                'active'  => (int) $counts->active,
                'trashed' => (int) $counts->trashed,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = $this->onlyGroupRepresentatives(GalleryItem::withTrashed(), GalleryItem::class)->selectRaw("
            SUM(CASE WHEN deleted_at IS NULL AND is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN deleted_at IS NULL AND is_active = 0 THEN 1 ELSE 0 END) as passive,
            SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as trashed
        ")->first();

        return [
            'active'  => (int) $counts->active,
            'passive' => (int) $counts->passive,
            'trashed' => (int) $counts->trashed,
        ];
    }

    private function clearCache(): void
    {
        $this->forgetLocalized('gallery.photos');
        $this->forgetLocalized('gallery.videos');
        Cache::forget('admin.gallery.stats');
        // Photos are listed in the sitemap as image entries, so a new or
        // removed one has to reach it straight away.
        Cache::forget('sitemap.urls');
    }
}
