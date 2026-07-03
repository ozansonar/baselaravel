<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * Blog kapak görseli — sadece MediaLibrary'den seçim.
 *
 * Ürün adına göre ilgili havuzdan, eşleşme yoksa genel havuzdan seçer.
 * AI görsel üretimi YAPILMAZ.
 *
 * KRİTİK: Bu servis HİÇBİR ZAMAN exception fırlatmaz — caller
 * (BlogGenerationService) blog yazısını her durumda yayınlayabilmeli.
 */
final class BlogCoverImageService
{
    public function __construct(
        private readonly MediaLibraryService $mediaLibrary,
    ) {}

    /**
     * Blog için kapak görseli seç.
     *
     * @param  ?string  $selectedProduct  AiPromptSetting.products'tan seçilen ürün adı.
     *                                    MediaLibrary bu ürünün görsellerinden seçer,
     *                                    bulamazsa genel havuza düşer.
     *
     * @return array{
     *     success: bool,
     *     image_path: ?string,
     *     verify_passed: bool,
     *     attempt_count: int,
     *     reason: string,
     *     prompt_used: ?string,
     * }
     */
    public function generateForBlog(BlogPost $post, ?string $selectedProduct = null): array
    {
        $autoEnabled = Setting::getValue('blog_auto_cover_image', '1') === '1';
        if (! $autoEnabled) {
            return $this->result(false, null, false, 0, 'Otomatik blog kapak görseli ayarlardan kapalı.');
        }

        try {
            return $this->pickFromLibrary($selectedProduct);
        } catch (\Throwable $e) {
            Log::warning('BlogCoverImageService: beklenmedik hata', [
                'blog_id' => $post->id,
                'error'   => $e->getMessage(),
            ]);

            return $this->result(false, null, false, 0, 'Beklenmedik hata: ' . $e->getMessage());
        }
    }

    /**
     * MediaLibrary'den seçim — sıfır AI maliyeti.
     *
     * Cascade:
     *   1. product_name = $selectedProduct (LRU+LFU top-5 random)
     *   2. product_name IS NULL (Genel Havuz)
     *   3. null → placeholder kalır
     *
     * @return array{
     *     success: bool,
     *     image_path: ?string,
     *     verify_passed: bool,
     *     attempt_count: int,
     *     reason: string,
     *     prompt_used: ?string,
     * }
     */
    private function pickFromLibrary(?string $selectedProduct): array
    {
        $asset = $this->mediaLibrary->pickFor($selectedProduct);

        if ($asset === null) {
            $label = $selectedProduct !== null && $selectedProduct !== ''
                ? "'{$selectedProduct}' ürünü için"
                : 'Genel havuzda';

            return $this->result(
                false, null, false, 0,
                "Kütüphanede {$label} aktif görsel yok — placeholder kullanılacak.",
            );
        }

        $asset->markUsed();

        $titleLabel = $asset->title !== null && $asset->title !== ''
            ? $asset->title
            : "asset #{$asset->id}";
        $productLabel = $asset->product_name ?? 'Genel';

        return $this->result(
            true,
            $asset->image_path,
            true,
            1,
            "Kütüphaneden seçildi ({$productLabel} havuzu): {$titleLabel}",
        );
    }

    /**
     * @return array{
     *     success: bool,
     *     image_path: ?string,
     *     verify_passed: bool,
     *     attempt_count: int,
     *     reason: string,
     *     prompt_used: ?string,
     * }
     */
    private function result(bool $success, ?string $imagePath, bool $verifyPassed, int $attempt, string $reason): array
    {
        return [
            'success'       => $success,
            'image_path'    => $imagePath,
            'verify_passed' => $verifyPassed,
            'attempt_count' => $attempt,
            'reason'        => $reason,
            'prompt_used'   => null,
        ];
    }
}
