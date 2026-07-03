<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiGeneratedImage;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Ai\AiImageGenerator;
use Illuminate\Support\Str;

/**
 * Thin wrapper around the unified `AiImageGenerator` for the /admin/ai-images
 * page and product photo enhancement.
 *
 * The base service owns: API key/toggle gating, model fallback chain, HTTP,
 * payload extraction, dimension probing, cost logging.
 */
final class AiImageService
{
    public function __construct(
        private readonly AiImageGenerator $generator,
    ) {}

    /**
     * Generate an image for the admin /ai-images page.
     *
     * @return array{success: bool, message: string, image: AiGeneratedImage}
     */
    public function generate(string $prompt, ?int $promptTemplateId = null, ?int $userId = null, string $aspectRatio = '1:1'): array
    {
        $result = $this->generator->generate(
            prompt: $prompt,
            aspectRatio: $aspectRatio,
            folder: 'ai-images',
            slugSuffix: 'gen',
            source: AiGeneratedImage::SOURCE_ADMIN_AI_IMAGES,
            userId: $userId,
            promptTemplateId: $promptTemplateId,
        );

        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'image'   => $result['image'],
        ];
    }

    /**
     * Enhance an existing product image and append the result to the gallery.
     *
     * @return array{success: bool, message: string, image_path?: string}
     */
    public function enhance(Product $product, string $sourceImagePath, string $style): array
    {
        $absolutePath = public_path('uploads/' . $sourceImagePath);

        $result = $this->generator->enhance(
            sourceAbsolutePath: $absolutePath,
            prompt: $this->buildEnhancePrompt($product, $style),
            folder: 'products/gallery',
            slugSuffix: Str::slug($product->name) . '-ai',
            source: AiGeneratedImage::SOURCE_PRODUCT_ENHANCE,
        );

        if ($result['success'] && ! empty($result['image_path'])) {
            $this->addToProductGallery($product, $result['image_path']);

            return [
                'success'    => true,
                'message'    => $result['message'],
                'image_path' => $result['image_path'],
            ];
        }

        return [
            'success' => false,
            'message' => $result['message'],
        ];
    }

    private function buildEnhancePrompt(Product $product, string $style): string
    {
        $base = "Bu görseldeki ürün: \"{$product->name}\". Orhan Babanın Çiftliği (Büyük Palabıyık Köyü, Çorum) ürünüdür.";

        return match ($style) {
            'studio'    => $base . ' Bu ürün fotoğrafını profesyonel stüdyo çekimi kalitesinde yeniden oluştur. Temiz beyaz arka plan, yumuşak stüdyo ışığı, ürün net ve parlak, gölgeler minimal. E-ticaret sitesi için ideal görünüm.',
            'natural'   => $base . ' Bu ürün fotoğrafını doğal köy ortamında yeniden oluştur. Ahşap masa veya çiftlik ortamı, doğal gün ışığı, rustik ama estetik, organik hissi veren sıcak tonlar.',
            'social'    => $base . ' Bu ürün fotoğrafını Instagram/sosyal medya için göz alıcı şekilde yeniden oluştur. Canlı renkler, modern flat-lay düzen, estetik arka plan, yüksek engagement potansiyeli.',
            'clean'     => $base . ' Bu görselin arka planını temizle, ürünü saf beyaz arka plan üzerine koy. Ürünün kendisini değiştirme, sadece arka planı beyaz yap. Profesyonel ürün kataloğu formatı.',
            'breakfast' => $base . ' Bu ürünü zengin bir Türk kahvaltı sofrası içinde göster. Çeşitli peynirler, zeytin, domates, salatalık, bal, tereyağı, simit, çay bardakları — geleneksel serpme kahvaltı düzeninde. Ürün ön planda ve belirgin, sabah güneşi ışığı, sıcak ev ortamı.',
            'harvest'   => $base . ' Bu ürünü hasat/toplama anında göster. Çorum köy arazisi, mevsime uygun tarla veya bahçe arka planı, doğal yeşillik, çiftçi elleri veya hasır sepet içinde ürün. Otantik, gerçekçi, organik hissi güçlü.',
            'wooden'    => $base . ' Bu ürünü rustik ahşap kesme tahtası veya eski köy masası üzerinde göster. Yanında bıçak, bez peçete, taze otlar (kekik, nane, maydanoz). Vintage doğal ışık, film tonu renk paleti, food photography estetiği.',
            'basket'    => $base . ' Bu ürünü hasır sepet veya ahşap kasa içinde, diğer doğal köy ürünleriyle birlikte göster. Pazar yeri veya çiftlik tezgahı ortamı, bolluk ve tazelik hissi, renkli sebze/meyve detayları, doğal gün ışığı.',
            'family'    => $base . ' Bu ürünü Türk aile sofrasında göster. Sıcak ev mutfağı, sofrada aile yemeği hazırlığı, anne eli veya çocuk sevinci. Samimi, güven veren, aile değeri ön planda. Yumuşak sıcak ışık, bokeh arka plan.',
            default     => $base . ' Bu ürün fotoğrafını daha profesyonel ve çekici hale getir. Işığı düzelt, renkleri canlılaştır, detayları keskinleştir.',
        };
    }

    private function addToProductGallery(Product $product, string $imagePath): void
    {
        $maxOrder = ProductImage::where('product_id', $product->id)->max('sort_order');

        ProductImage::create([
            'product_id' => $product->id,
            'image'      => $imagePath,
            'sort_order' => ($maxOrder !== null ? (int) $maxOrder : 0) + 1,
        ]);
    }
}
