<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SpecialDay;
use App\Models\SpecialDayImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Bir özel gün için galeriden akıllı görsel seçimi.
 *
 * Algoritma:
 *   1. media_type'a göre filtrele (story/feed/reels)
 *   2. usage_count ASC (en az kullanılan)
 *   3. last_used_at ASC NULLS FIRST (hiç kullanılmamış önce, sonra en eski)
 *   4. sort_order ASC, id ASC (deterministic tie-break)
 *
 * Recycle tespit + Telegram bildirim:
 *   - Seçilen görselin usage_count > 0 ise: galerideki tüm görseller en az 1 kez
 *     kullanılmış demektir → recycle başladı → admin'e Telegram bildirimi
 *   - Throttle: aynı (özel_gün_id + media_type) için günde 1 bildirim
 */
final class SpecialDayImageSelector
{
    /**
     * Tek görsel seç (Story/Feed/Reels).
     * Galeride o tipte görsel yoksa null döner — caller AI fallback veya hata göstermeli.
     */
    public function pickOne(SpecialDay $day, string $mediaType): ?SpecialDayImage
    {
        $image = $day->images()
            ->ofMediaType($mediaType)
            ->leastUsed()
            ->first();

        if ($image === null) {
            return null;
        }

        // Recycle tespiti: seçilen görsel daha önce kullanılmışsa
        if ($image->usage_count > 0) {
            $this->notifyRecycle($day, $mediaType, $image);
        }

        return $image;
    }

    /**
     * Carousel için N görsel seç (Feed). En az kullanılanlardan başlayıp N tane döner.
     * Galeride yetersiz sayıda görsel varsa mevcutlar dönülür (recycle dahil).
     *
     * @return Collection<int, SpecialDayImage>
     */
    public function pickMultiple(SpecialDay $day, string $mediaType, int $count): Collection
    {
        $count = max(1, min($count, 10)); // IG carousel max 10

        /** @var Collection<int, SpecialDayImage> $available */
        $available = $day->images()
            ->ofMediaType($mediaType)
            ->leastUsed()
            ->get();

        if ($available->isEmpty()) {
            return collect();
        }

        // Yeterli görsel varsa direkt al
        if ($available->count() >= $count) {
            return $available->take($count);
        }

        // Yetersiz — mevcutları döndür + recycle bildirimi at
        Log::info('SpecialDayImageSelector: yetersiz galeri, mevcut görseller recycle edilecek', [
            'special_day_id' => $day->id,
            'requested'      => $count,
            'available'      => $available->count(),
        ]);

        // İhtiyaç duyulan ek sayı kadar recycle (en az kullanılanlardan tekrar)
        $result = $available->all();
        $i = 0;
        while (count($result) < $count) {
            $result[] = $available[$i % $available->count()];
            $i++;
        }

        // Recycle bildirimi
        $this->notifyInsufficientGallery($day, $mediaType, $count, $available->count());

        return collect($result);
    }

    /**
     * Bir görseli "kullanıldı" olarak işaretler — usage_count++ + last_used_at=now.
     * ProcessBulkImportRowJob bu metodu post oluşturduktan sonra çağırır.
     */
    public function markUsed(SpecialDayImage $image): void
    {
        $image->update([
            'usage_count'  => $image->usage_count + 1,
            'last_used_at' => now(),
        ]);
    }

    private function notifyRecycle(SpecialDay $day, string $mediaType, SpecialDayImage $image): void
    {
        $detailUrl = rescue(
            static fn (): ?string => route('admin.special-days.gallery', $day->id),
            null,
            false,
        );

        TelegramNotifier::notifyAdminError(
            'Özel gün görseli recycle edildi — yeni görsel ekle',
            [
                'gun'        => $day->name . ' (' . $day->date->format('d.m.Y') . ')',
                'tip'        => $mediaType,
                'kullanilan' => basename((string) $image->image_path),
                'kez'        => 'Toplam ' . ($image->usage_count + 1) . '. kez kullanıldı',
                'oneri'      => 'Yeni görsel ekleyerek galeriyi büyüt',
            ],
            $detailUrl,
            emoji: '🔄',
            cacheKey: "sd_recycle:{$day->id}:{$mediaType}:" . now()->format('Y-m-d'),
        );
    }

    private function notifyInsufficientGallery(SpecialDay $day, string $mediaType, int $requested, int $available): void
    {
        $detailUrl = rescue(
            static fn (): ?string => route('admin.special-days.gallery', $day->id),
            null,
            false,
        );

        TelegramNotifier::notifyAdminError(
            'Özel gün galerisi yetersiz — recycle yapıldı',
            [
                'gun'         => $day->name . ' (' . $day->date->format('d.m.Y') . ')',
                'tip'         => $mediaType,
                'istenen'     => $requested . ' görsel',
                'mevcut'      => $available . ' görsel',
                'recycle'     => ($requested - $available) . ' görsel tekrar kullanıldı',
            ],
            $detailUrl,
            emoji: '⚠️',
            cacheKey: "sd_insufficient:{$day->id}:{$mediaType}:" . now()->format('Y-m-d'),
        );
    }
}
