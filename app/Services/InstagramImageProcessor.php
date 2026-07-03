<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Instagram için görsel işleme — center crop + resize.
 *
 * Akış:
 *   1. Source UploadedFile alır
 *   2. Hedef boyutu media_type + source orana göre belirle
 *   3. Center crop (veya manuel pixel crop) ile target ratio'ya getir
 *   4. Target piksel boyutlarına resample
 *   5. WebP olarak temp dosyaya kaydet
 *   6. UploadedFile sarmalı döner — UploadService::uploadImage'a beslenir
 *
 * Sonuç pipeline'a beslenir: WebP convert + responsive variants
 * (zaten uploadImage yapıyor, bu yüzden burada sadece tek dosya üretiyoruz).
 */
final class InstagramImageProcessor
{
    private const int MIN_DIMENSION = 320;
    private const int WEBP_QUALITY = 90;

    // GD source görsel ~ width × height × 4 bytes RAM kullanır.
    // 25 megapixel = ~100MB peak memory — varsayılan 128M memory_limit sınıra yaklaşır.
    // Çok yüksek çözünürlüklü görsel reddedilir (kullanıcı önceden boyutlandırmalı).
    private const int MAX_PIXEL_COUNT = 25_000_000;

    /**
     * Auto mode — center crop ile media_type'a uygun orana getir.
     *
     * @throws RuntimeException
     */
    public function autoFix(UploadedFile $file, string $mediaType): UploadedFile
    {
        $this->assertImage($file);

        $src = $this->createGdImage($file);
        try {
            $srcW = imagesx($src);
            $srcH = imagesy($src);

            if ($srcW < self::MIN_DIMENSION || $srcH < self::MIN_DIMENSION) {
                throw new RuntimeException(sprintf(
                    'Görsel çok küçük (%d×%d). Minimum %d×%d gerekli — büyütme yapılmaz (kalite kaybı).',
                    $srcW, $srcH, self::MIN_DIMENSION, self::MIN_DIMENSION,
                ));
            }

            if ($srcW * $srcH > self::MAX_PIXEL_COUNT) {
                throw new RuntimeException(sprintf(
                    'Görsel çok yüksek çözünürlük (%d×%d = %.0f MP). Sunucu memory aşar. ' .
                    'Lütfen önceden ~5000×5000 altına düşür.',
                    $srcW, $srcH, ($srcW * $srcH) / 1_000_000,
                ));
            }

            ['width' => $targetW, 'height' => $targetH] = $this->resolveTargetDimensions($srcW, $srcH, $mediaType);

            // Center crop koordinatları
            ['x' => $cropX, 'y' => $cropY, 'w' => $cropW, 'h' => $cropH] = $this->computeCenterCrop($srcW, $srcH, $targetW / $targetH);

            return $this->resampleAndSave($src, $cropX, $cropY, $cropW, $cropH, $targetW, $targetH, $file->getClientOriginalName());
        } finally {
            imagedestroy($src);
        }
    }

    /**
     * Manual mode — kullanıcının seçtiği pixel koordinatlarıyla crop.
     *
     * @param  array{x: int, y: int, width: int, height: int}  $cropData
     * @throws RuntimeException
     */
    public function manualCrop(UploadedFile $file, string $mediaType, array $cropData): UploadedFile
    {
        $this->assertImage($file);

        $src = $this->createGdImage($file);
        try {
            $srcW = imagesx($src);
            $srcH = imagesy($src);

            $cropX = max(0, (int) ($cropData['x'] ?? 0));
            $cropY = max(0, (int) ($cropData['y'] ?? 0));
            $cropW = max(1, (int) ($cropData['width']  ?? 0));
            $cropH = max(1, (int) ($cropData['height'] ?? 0));

            // Bounds kontrolü
            if ($cropX + $cropW > $srcW || $cropY + $cropH > $srcH) {
                throw new RuntimeException('Kırpma alanı görsel sınırlarını aşıyor.');
            }

            if ($cropW < self::MIN_DIMENSION || $cropH < self::MIN_DIMENSION) {
                throw new RuntimeException(sprintf(
                    'Kırpma alanı çok küçük (%d×%d). Min %d×%d olmalı.',
                    $cropW, $cropH, self::MIN_DIMENSION, self::MIN_DIMENSION,
                ));
            }

            // Hedef boyut: kırpma oranına göre standart 1080 genişliğe oturt
            $cropRatio = $cropW / $cropH;
            $targetW = 1080;
            $targetH = (int) round($targetW / $cropRatio);

            // Story için zorla 1080×1920 (kullanıcı 9:16 dışında crop yapsa bile düzelt)
            if ($mediaType === 'story') {
                $targetW = 1080;
                $targetH = 1920;
            }

            return $this->resampleAndSave($src, $cropX, $cropY, $cropW, $cropH, $targetW, $targetH, $file->getClientOriginalName());
        } finally {
            imagedestroy($src);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Internal helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * @return array{width: int, height: int}
     */
    private function resolveTargetDimensions(int $srcW, int $srcH, string $mediaType): array
    {
        if ($mediaType === 'story') {
            return ['width' => 1080, 'height' => 1920];
        }

        // Image (Feed): source orana göre 3 standart preset
        $ratio = $srcW / $srcH;

        if ($ratio < 0.95) {
            return ['width' => 1080, 'height' => 1350]; // 4:5 dikey
        }
        if ($ratio > 1.05) {
            return ['width' => 1080, 'height' => 566];  // 1.91:1 yatay
        }

        return ['width' => 1080, 'height' => 1080]; // 1:1 kare
    }

    /**
     * Source görselden target ratio'ya uyacak şekilde center crop koordinatları.
     *
     * @return array{x: int, y: int, w: int, h: int}
     */
    private function computeCenterCrop(int $srcW, int $srcH, float $targetRatio): array
    {
        $srcRatio = $srcW / $srcH;

        if ($srcRatio > $targetRatio) {
            // Source daha yatay → sol/sağ kırp
            $cropH = $srcH;
            $cropW = (int) round($srcH * $targetRatio);
            $cropX = (int) round(($srcW - $cropW) / 2);
            $cropY = 0;
        } else {
            // Source daha dikey (veya eşit) → üst/alt kırp
            $cropW = $srcW;
            $cropH = (int) round($srcW / $targetRatio);
            $cropX = 0;
            $cropY = (int) round(($srcH - $cropH) / 2);
        }

        return ['x' => $cropX, 'y' => $cropY, 'w' => $cropW, 'h' => $cropH];
    }

    /**
     * Source GD image'i crop + resample edip WebP olarak temp dosyaya kaydet,
     * UploadedFile sarmalı döner.
     *
     * @throws RuntimeException
     */
    private function resampleAndSave(
        \GdImage $src,
        int $cropX, int $cropY, int $cropW, int $cropH,
        int $targetW, int $targetH,
        string $originalName,
    ): UploadedFile {
        $dst = imagecreatetruecolor($targetW, $targetH);
        if ($dst === false) {
            throw new RuntimeException('Hedef görsel oluşturulamadı (memory_limit aşılmış olabilir).');
        }

        // Şeffaflık desteği (WebP'de işe yarar)
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        // Şeffaf arka plan
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $transparent);

        $resampled = imagecopyresampled(
            $dst, $src,
            0, 0,
            $cropX, $cropY,
            $targetW, $targetH,
            $cropW, $cropH,
        );

        if (! $resampled) {
            imagedestroy($dst);
            throw new RuntimeException('Görsel resample edilemedi.');
        }

        // tempnam unique boş dosya yaratır + permission verir; biz üzerine WebP yazıyoruz
        // (rename + race condition daha karmaşık)
        $webpPath = tempnam(sys_get_temp_dir(), 'ig-crop-');
        if ($webpPath === false) {
            imagedestroy($dst);
            throw new RuntimeException('Geçici dosya oluşturulamadı.');
        }

        $saved = imagewebp($dst, $webpPath, self::WEBP_QUALITY);
        imagedestroy($dst);

        if (! $saved) {
            @unlink($webpPath);
            throw new RuntimeException('WebP olarak kaydedilemedi.');
        }

        // UploadedFile sarmalı (test=true → upload mover izin verir)
        $cleanName = pathinfo($originalName, PATHINFO_FILENAME) ?: 'cropped';
        $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '-', $cleanName) ?? 'cropped';

        return new UploadedFile(
            $webpPath,
            "{$cleanName}-cropped.webp",
            'image/webp',
            null,
            true,
        );
    }

    /**
     * @throws RuntimeException
     */
    private function assertImage(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Yüklenen dosya geçersiz.');
        }
        $mime = (string) $file->getMimeType();
        if (! str_starts_with($mime, 'image/')) {
            throw new RuntimeException("Yüklenen dosya görsel değil ({$mime}).");
        }
    }

    /**
     * @throws RuntimeException
     */
    private function createGdImage(UploadedFile $file): \GdImage
    {
        $mime = $file->getMimeType();
        $path = $file->getRealPath() ?: $file->getPathname();

        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/gif'  => @imagecreatefromgif($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/bmp'  => @imagecreatefrombmp($path),
            default      => false,
        };

        if (! $image instanceof \GdImage) {
            throw new RuntimeException("Görsel okunamadı (bozuk veya desteklenmeyen format: {$mime}).");
        }

        // Şeffaflık koru
        if (in_array($mime, ['image/png', 'image/gif', 'image/webp'], true)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        return $image;
    }
}
