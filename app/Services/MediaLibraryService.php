<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Görsel Kütüphanesi orchestrator'ü.
 *
 * Cron blog yazarken hangi ürünü seçtiyse o ürünün görsellerinden birini
 * (LRU + LFU sıralaması ile top-5'ten rastgele) seçer. Ürün eşleşmesi
 * yoksa "Genel" havuza, o da boşsa null döner — caller (BlogCoverImageService)
 * placeholder fallback'i kullanır. AI çağrısı YOK.
 *
 * Tüm yükleme/silme işlemleri DB::transaction içinde — dosya sistemi
 * kayıt + DB insert atomik. Stats cache (5 dk) upload/delete'te invalidate.
 */
final class MediaLibraryService
{
    private const string UPLOAD_FOLDER = 'media-library';

    /** Stats cache key — `statsByProduct()` çıktısını saklar. */
    private const string STATS_CACHE_KEY = 'media_library.stats';

    /** Stats cache süresi (saniye). */
    private const int STATS_CACHE_TTL = 300; // 5 dk

    /** Smart selection için kuyruk büyüklüğü. */
    private const int SELECTION_POOL_SIZE = 5;

    public function __construct(
        private readonly UploadService $uploadService,
        private readonly AiPromptSettingService $aiPromptSettingService,
    ) {}

    /**
     * Cron blog kapağı için görsel seçer.
     *
     * Cascade:
     *   1. WHERE product_name = $productName  (LRU + LFU + top-5 random)
     *   2. WHERE product_name IS NULL          (Genel havuz, aynı sıralama)
     *   3. null                                (caller placeholder kullanır)
     *
     * Seçilen asset'in `usage_count`/`last_used_at`'i DAHA SONRA caller
     * tarafından `markUsed()` ile güncellenmelidir — bu metot side-effect
     * üretmez (test edilebilirlik için).
     */
    public function pickFor(?string $productName): ?MediaAsset
    {
        if ($productName !== null && $productName !== '') {
            // 1. Birebir eşleşme
            $asset = $this->smartSelect(
                MediaAsset::query()->active()->forProduct($productName),
            );
            if ($asset !== null) {
                Log::info('MediaLibrary pickFor: birebir ürün eşleşmesi', [
                    'product_name' => $productName,
                    'asset_id'     => $asset->id,
                ]);

                return $asset;
            }

            // 2. Fuzzy eşleşme — ürün adları farklı kaynaklardan geldiğinde
            //    (products.name vs AiPromptSetting.products) anahtar kelime
            //    tabanlı eşleştirme yapar.
            $fuzzyProduct = $this->fuzzyMatchProduct($productName);
            if ($fuzzyProduct !== null) {
                $asset = $this->smartSelect(
                    MediaAsset::query()->active()->forProduct($fuzzyProduct),
                );
                if ($asset !== null) {
                    Log::info('MediaLibrary pickFor: fuzzy ürün eşleşmesi', [
                        'requested'     => $productName,
                        'matched'       => $fuzzyProduct,
                        'asset_id'      => $asset->id,
                    ]);

                    return $asset;
                }
            }
        }

        // 3. Genel havuz (product_name IS NULL)
        $asset = $this->smartSelect(
            MediaAsset::query()->active()->general(),
        );
        if ($asset !== null) {
            Log::info('MediaLibrary pickFor: genel havuzdan seçildi', [
                'requested_product' => $productName,
                'asset_id'          => $asset->id,
            ]);

            return $asset;
        }

        // 4. Son çare: herhangi bir aktif görsel (ürün fark etmez)
        $asset = $this->smartSelect(
            MediaAsset::query()->active(),
        );
        if ($asset !== null) {
            Log::info('MediaLibrary pickFor: son çare — herhangi bir aktif görselden seçildi', [
                'requested_product' => $productName,
                'asset_id'          => $asset->id,
                'asset_product'     => $asset->product_name,
            ]);

            return $asset;
        }

        Log::warning('MediaLibrary pickFor: hiç aktif görsel bulunamadı', [
            'requested_product' => $productName,
            'total_assets'      => MediaAsset::count(),
            'active_assets'     => MediaAsset::where('is_active', true)->count(),
        ]);

        return null;
    }

    /**
     * Ürün adı fuzzy eşleştirme.
     *
     * products.name ("Günlük Çiğ Süt") ile media_assets.product_name
     * ("Günlük Taze Süt") farklı kaynaklardan geldiğinde birebir eşleşme
     * başarısız olur. Bu metot anahtar kelime bazlı eşleştirme yapar:
     *
     *   1. Substring: "Süt" ↔ "Günlük Taze Süt" (ya da tersi)
     *   2. Keyword overlap: ortak anlamlı kelime varsa eşleş
     *
     * @return ?string  Eşleşen media_assets.product_name değeri (exact DB value)
     */
    private function fuzzyMatchProduct(string $requestedProduct): ?string
    {
        /** @var list<string> $assetProducts */
        $assetProducts = MediaAsset::query()
            ->active()
            ->whereNotNull('product_name')
            ->where('product_name', '!=', '')
            ->distinct()
            ->pluck('product_name')
            ->all();

        if (empty($assetProducts)) {
            return null;
        }

        $requestedLower = mb_strtolower($requestedProduct, 'UTF-8');

        // 1. Substring — birinin adı diğerinin içinde geçiyor mu?
        foreach ($assetProducts as $assetProduct) {
            $assetLower = mb_strtolower((string) $assetProduct, 'UTF-8');

            if (str_contains($requestedLower, $assetLower) || str_contains($assetLower, $requestedLower)) {
                return $assetProduct;
            }
        }

        // 2. Keyword overlap — anlamlı kelimelerde (3+ karakter) kesişim.
        //    Skor = eşleşen kelimelerin toplam karakter uzunluğu.
        //    "Yumurtası" (9 harf) > "Köy" (3 harf) → daha spesifik kelime kazanır.
        $requestedWords = $this->extractSignificantWords($requestedProduct);

        $bestMatch = null;
        $bestScore = 0;

        foreach ($assetProducts as $assetProduct) {
            $assetWords = $this->extractSignificantWords((string) $assetProduct);
            $matching = array_intersect($requestedWords, $assetWords);

            $score = array_sum(array_map(
                static fn (string $w): int => mb_strlen($w, 'UTF-8'),
                $matching,
            ));

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $assetProduct;
            }
        }

        if ($bestMatch !== null && $bestScore > 0) {
            Log::info('MediaLibrary fuzzyMatch: keyword overlap', [
                'requested' => $requestedProduct,
                'matched'   => $bestMatch,
                'score'     => $bestScore,
            ]);

            return $bestMatch;
        }

        return null;
    }

    /**
     * Ürün adından anlamlı kelimeleri çıkar (lowercase, 3+ karakter).
     *
     * @return list<string>
     */
    private function extractSignificantWords(string $text): array
    {
        $words = preg_split('/[\s()\[\],;.]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false) {
            return [];
        }

        return array_values(array_map(
            static fn (string $w): string => mb_strtolower($w, 'UTF-8'),
            array_filter($words, static fn (string $w): bool => mb_strlen($w, 'UTF-8') >= 3),
        ));
    }

    /**
     * LRU + LFU sıralı top-N'den rastgele bir asset seç.
     *
     * Önce hiç kullanılmamış görseller, sonra en uzun süredir kullanılmamış,
     * sonra en az sayıda kullanılmış. Aynı sıralamadakilerden rastgele
     * birini almak için top-5'i alıp PHP tarafında shuffle ediyoruz —
     * böylece 70 görsel havuzunda tekdüzelik olmaz.
     *
     * @param  Builder<MediaAsset>  $query
     */
    public function smartSelect(Builder $query): ?MediaAsset
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, MediaAsset> $candidates */
        $candidates = $query
            ->leastUsed()
            ->limit(self::SELECTION_POOL_SIZE)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->random();
    }

    /**
     * Görsel yükle — dosya sistemi + DB satırı atomik.
     *
     * @param  UploadedFile  $file        Yüklenen görsel (validation öncesi kare 1:1 ≥1080×1080 garanti)
     * @param  ?string       $productName AiPromptSetting.products'tan biri, null = "Genel"
     * @param  ?string       $title       SEO alt text + admin için açıklama
     * @param  ?int          $userId      auth()->id() (created_by için)
     */
    public function uploadAsset(
        UploadedFile $file,
        ?string $productName,
        ?string $title,
        ?int $userId = null,
    ): MediaAsset {
        return DB::transaction(function () use ($file, $productName, $title, $userId): MediaAsset {
            // SEO-friendly dosya adı: orhan-babanin-ciftligi-{ürün-slug}-{uniqueKey}.{ext}
            // Ürün-bağımsız (Genel) yüklemeler için "genel" suffix.
            $brandSlug = \Illuminate\Support\Str::slug((string) config('app.name', 'orhan-babanin-ciftligi'));
            $productSlug = \Illuminate\Support\Str::slug($productName ?? 'genel');
            $slugSeed = "{$brandSlug}-{$productSlug}";

            // 1. UploadService → public/uploads/media-library/ (WebP convert + responsive variants)
            //    uploadImage() bu seed'e Str::random(8) uniqueKey ve uzantı ekler:
            //    orhan-babanin-ciftligi-dogal-koy-tereyagi-a1b2c3d4.webp
            $imagePath = $this->uploadService->uploadImage(
                $file,
                self::UPLOAD_FOLDER,
                $slugSeed,
            );

            // 2. Boyut probe — DB'de width/height saklanır (validation kareyi zaten doğruladı)
            $absolute = public_path('uploads/' . $imagePath);
            $info = @getimagesize($absolute);

            // 3. DB satırı
            $asset = MediaAsset::create([
                'product_name' => $productName,
                'title'        => $title,
                'image_path'   => $imagePath,
                'width'        => $info !== false ? (int) $info[0] : null,
                'height'       => $info !== false ? (int) $info[1] : null,
                'created_by'   => $userId,
            ]);

            // 4. Stats cache invalidate
            Cache::forget(self::STATS_CACHE_KEY);

            return $asset;
        });
    }

    /**
     * Görseli sil — dosya sistemi + soft-delete atomik.
     *
     * Soft delete tercih edildi (CLAUDE.md zorunluluğu) ama dosya
     * fiziksel olarak silinir; restore edilse bile image_path geçersiz
     * olur. İleride dosyayı da soft tutmak istenirse `deleteAsset(force=false)`
     * eklenebilir.
     */
    public function deleteAsset(MediaAsset $asset): void
    {
        DB::transaction(function () use ($asset): void {
            if ($asset->image_path !== '') {
                try {
                    $this->uploadService->deleteImage($asset->image_path);
                } catch (\Throwable $e) {
                    // Dosya bulunamadıysa devam et — DB temizliği daha önemli
                    Log::warning('MediaLibraryService::deleteAsset — dosya silinemedi', [
                        'asset_id' => $asset->id,
                        'path'     => $asset->image_path,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            $asset->delete();

            Cache::forget(self::STATS_CACHE_KEY);
        });
    }

    /**
     * Ürün başına aktif görsel sayısı (admin grid için).
     *
     * Çıktı:
     *   [
     *       'Günlük Taze Süt'    => 12,
     *       'Doğal Köy Tereyağı' => 8,
     *       'Genel'              => 25,   // null → "Genel" label'ına map
     *       ...
     *   ]
     *
     * Cache 5 dk; uploadAsset/deleteAsset'te invalidate.
     *
     * @return array<string, int>
     */
    public function statsByProduct(): array
    {
        return Cache::remember(self::STATS_CACHE_KEY, self::STATS_CACHE_TTL, function (): array {
            $rows = MediaAsset::query()
                ->active()
                ->selectRaw('product_name, COUNT(*) as total')
                ->groupBy('product_name')
                ->get();

            $stats = [];
            foreach ($rows as $row) {
                $key = $row->product_name ?? 'Genel';
                $stats[$key] = (int) $row->total;
            }

            // AiPromptSetting.products listesindeki tüm ürünleri 0 ile başlat
            // (henüz görseli olmayan ürünler de grid'de görünsün).
            foreach ($this->availableProducts() as $product) {
                if (! isset($stats[$product])) {
                    $stats[$product] = 0;
                }
            }
            // "Genel" her zaman gözüksün
            $stats['Genel'] = $stats['Genel'] ?? 0;

            return $stats;
        });
    }

    /**
     * AiPromptSetting.products listesi — yükleme dropdown'ında kullanılır.
     * Tek kaynak: AiPromptSetting. MediaLibrary kendi ürün listesi tutmaz.
     *
     * @return list<string>
     */
    public function availableProducts(): array
    {
        $setting = $this->aiPromptSettingService->get();

        return array_values(array_filter(
            (array) ($setting->products ?? []),
            static fn ($p): bool => is_string($p) && $p !== '',
        ));
    }
}
