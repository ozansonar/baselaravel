<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MediaLibraryImage;
use App\Services\MediaLibraryAutoTagger;
use App\Services\TelegramNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Görsel kütüphanesindeki etiketsiz görsellere toplu AI auto-tag uygular.
 *
 * Akış:
 *   - Verilen image ID'leri için tek tek MediaLibraryAutoTagger::analyzeImage çağırır
 *   - Başarılı sonuçları DB'ye yazar (tags + theme + mood + season + description + alt_text)
 *   - ai_generated_tags=true işaretler
 *   - Bittiğinde Telegram'a özet bildirimi atar (X başarılı, Y hata, ~$Z maliyet)
 *
 * Maliyet: ~$0.00015 × N görsel (örn. 100 görsel = ~$0.015)
 */
final class BatchAutoTagImagesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800; // 30 dk (100 görsel × ~5sn = ~8dk + buffer)

    /**
     * @param list<int> $imageIds
     */
    public function __construct(
        public readonly array $imageIds,
        public readonly ?int $triggeredBy = null,
    ) {}

    public function handle(MediaLibraryAutoTagger $tagger): void
    {
        $total = count($this->imageIds);
        if ($total === 0) return;

        $success = 0;
        $failed  = 0;
        $errors  = [];

        Log::info('BatchAutoTagImagesJob: başladı', ['total' => $total]);

        foreach ($this->imageIds as $id) {
            $img = MediaLibraryImage::find($id);
            if ($img === null) {
                $failed++;
                continue;
            }

            $absolutePath = public_path('uploads/' . $img->image_path);
            $result = $tagger->analyzeImage($absolutePath);

            if (! ($result['success'] ?? false)) {
                $failed++;
                $errors[] = "#{$id}: " . ($result['message'] ?? 'unknown');
                if (count($errors) > 10) array_shift($errors); // sadece son 10
                continue;
            }

            $img->update([
                'tags'              => ! empty($result['tags']) ? $result['tags'] : $img->tags,
                'theme'             => $result['theme']  ?: $img->theme,
                'mood'              => $result['mood']   ?: $img->mood,
                'season'            => $result['season'] ?: $img->season,
                'description'       => $result['description'] ?: $img->description,
                'alt_text'          => $result['alt_text'] ?: $img->alt_text,
                'ai_generated_tags' => true,
            ]);

            $success++;
        }

        $estCost = round($success * MediaLibraryAutoTagger::COST_PER_IMAGE, 4);

        Log::info('BatchAutoTagImagesJob: tamamlandı', [
            'success' => $success,
            'failed'  => $failed,
            'cost'    => $estCost,
        ]);

        $detailUrl = rescue(static fn (): ?string => route('admin.image-library.index'), null, false);

        TelegramNotifier::notifyAdminError(
            'Toplu Auto-Tag tamamlandı',
            [
                'tag_lendi' => "{$success} görsel",
                'hatali'    => "{$failed} görsel",
                'maliyet'   => '$' . number_format($estCost, 4),
                'son_hatalar' => $errors !== [] ? implode(' | ', $errors) : '—',
            ],
            $detailUrl,
            emoji: '✅',
            cacheKey: 'batch_autotag:' . md5(implode(',', $this->imageIds)),
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('BatchAutoTagImagesJob: kritik hata', ['error' => $exception->getMessage()]);

        TelegramNotifier::notifyAdminError(
            'Toplu Auto-Tag KRİTİK HATA',
            [
                'gorsel_sayisi' => count($this->imageIds),
                'hata'          => $exception->getMessage(),
            ],
            null,
            emoji: '🚨',
        );
    }
}
