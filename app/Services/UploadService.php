<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ShellExec;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

final class UploadService
{
    /**
     * Responsive image size definitions.
     *
     * @var array<string, array{width: int, height: ?int, crop: bool}>
     */
    private const SIZES = [
        'thumb' => ['width' => 150,  'height' => 150,  'crop' => true],
        'sm'    => ['width' => 300,  'height' => null,  'crop' => false],
        'md'    => ['width' => 600,  'height' => null,  'crop' => false],
        'lg'    => ['width' => 1200, 'height' => null,  'crop' => false],
    ];

    private const WEBP_QUALITY = 85;

    private const ORIGINAL_MAX_WIDTH = 1920;

    private const UNIQUE_KEY_LENGTH = 10;

    /**
     * Allowed image mime types for upload.
     *
     * @var array<string>
     */
    private const ALLOWED_IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
    ];

    /**
     * Allowed video mime types (mp4/quicktime).
     *
     * @var array<string>
     */
    private const ALLOWED_VIDEO_MIMES = [
        'video/mp4',
        'video/quicktime',
    ];

    /**
     * Maximum video file size (bytes) ~100MB.
     */
    private const MAX_VIDEO_SIZE_BYTES = 104_857_600; // 100 MB

    // ──────────────────────────────────────────────
    //  PUBLIC API
    // ──────────────────────────────────────────────

    /**
     * Upload an image, convert to WebP, and create responsive variants.
     *
     * @param  UploadedFile       $file           Uploaded image file
     * @param  string             $folder         Sub-folder inside public/uploads (e.g. "images")
     * @param  string             $name           Human-readable name for slug (e.g. "Organik Köy Sütü")
     * @param  array<string>|null $sizes          Which variants to create (null = all)
     * @param  bool               $preserveFormat Keep original format instead of converting to WebP
     * @return string                             Relative path: "images/example-a1b2c3d4e5.webp"
     */
    public function uploadImage(
        UploadedFile $file,
        string $folder,
        string $name,
        ?array $sizes = null,
        ?int $maxWidth = null,
        ?int $maxHeight = null,
        bool $preserveFormat = false,
    ): string {
        $this->validateImage($file);

        $sizes ??= array_keys(self::SIZES);
        $slug = Str::slug($name);
        $uniqueKey = Str::lower(Str::random(self::UNIQUE_KEY_LENGTH));

        $extension = $preserveFormat
            ? strtolower($file->getClientOriginalExtension())
            : 'webp';
        $mime = $file->getMimeType();
        $filename = "{$slug}-{$uniqueKey}.{$extension}";

        $directory = $this->uploadsPath($folder);
        $this->ensureDirectoryExists($directory);

        // Create GD resource from uploaded file
        $source = $this->createGdImage($file);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        $effectiveMaxWidth = $maxWidth ?? self::ORIGINAL_MAX_WIDTH;
        $saveFn = $preserveFormat
            ? fn (\GdImage $img, string $path) => $this->saveInOriginalFormat($img, $path, $mime)
            : fn (\GdImage $img, string $path) => $this->saveAsWebp($img, $path);

        // Fit within bounding box if both maxWidth and maxHeight given
        if ($maxWidth !== null && $maxHeight !== null) {
            if ($sourceWidth > $maxWidth || $sourceHeight > $maxHeight) {
                $resized = $this->fitResize($source, $sourceWidth, $sourceHeight, $maxWidth, $maxHeight);
                $saveFn($resized, "{$directory}/{$filename}");
                imagedestroy($resized);
            } else {
                $saveFn($source, "{$directory}/{$filename}");
            }
        } elseif ($sourceWidth > $effectiveMaxWidth) {
            $resized = $this->proportionalResize($source, $sourceWidth, $sourceHeight, $effectiveMaxWidth);
            $saveFn($resized, "{$directory}/{$filename}");
            imagedestroy($resized);
        } else {
            $saveFn($source, "{$directory}/{$filename}");
        }

        // Create responsive variants
        foreach ($sizes as $sizeName) {
            if (! isset(self::SIZES[$sizeName])) {
                continue;
            }

            $config = self::SIZES[$sizeName];

            // Skip if original is smaller than target
            if ($sourceWidth <= $config['width']) {
                continue;
            }

            $variant = $config['crop']
                ? $this->cropResize($source, $sourceWidth, $sourceHeight, $config['width'], $config['height'] ?? $config['width'])
                : $this->proportionalResize($source, $sourceWidth, $sourceHeight, $config['width']);

            $variantFilename = "{$slug}-{$uniqueKey}-{$sizeName}.{$extension}";
            $saveFn($variant, "{$directory}/{$variantFilename}");
            imagedestroy($variant);
        }

        imagedestroy($source);

        return "{$folder}/{$filename}";
    }

    /**
     * Upload a non-image file (PDF, document, etc.) without conversion.
     *
     * @param  UploadedFile $file   Uploaded file
     * @param  string       $folder Sub-folder inside public/uploads
     * @param  string       $name   Human-readable name for slug
     * @return string               Relative path: "documents/example-a1b2c3d4e5.pdf"
     */
    public function uploadFile(
        UploadedFile $file,
        string $folder,
        string $name,
    ): string {
        $slug = Str::slug($name);
        $uniqueKey = Str::lower(Str::random(self::UNIQUE_KEY_LENGTH));
        $extension = $file->getClientOriginalExtension();
        $filename = "{$slug}-{$uniqueKey}.{$extension}";

        $directory = $this->uploadsPath($folder);
        $this->ensureDirectoryExists($directory);

        $file->move($directory, $filename);

        return "{$folder}/{$filename}";
    }

    /**
     * Upload a video file.
     *
     * Validation: mp4/quicktime MIME + max 100MB. Süre kontrolü FFprobe yoksa atlanır.
     *
     * @param  UploadedFile $file
     * @param  string       $folder Sub-folder (e.g. "videos")
     * @param  string       $name   Slug için isim
     * @return string               Relative path: "videos/example-a1b2c3d4e5.mp4"
     *
     * @throws RuntimeException Validation hatası
     */
    public function uploadVideo(
        UploadedFile $file,
        string $folder,
        string $name,
    ): string {
        $this->validateVideo($file);

        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'video';
        }

        $uniqueKey = Str::lower(Str::random(self::UNIQUE_KEY_LENGTH));
        // Standardize: .mov → .mp4 ile yeniden adlandır (extension cosmetic, içerik aynı container'a uyumlu)
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension === '' || ! in_array($extension, ['mp4', 'mov'], true)) {
            $extension = 'mp4';
        }
        $filename = "{$slug}-{$uniqueKey}.{$extension}";

        $directory = $this->uploadsPath($folder);
        $this->ensureDirectoryExists($directory);

        $file->move($directory, $filename);

        return "{$folder}/{$filename}";
    }

    /**
     * Video dosyası süresini saniye cinsinden tahmin et (FFprobe varsa).
     * FFprobe yoksa null döner — UI'da gösterim opsiyonel.
     */
    public function probeVideoDuration(string $relativePath): ?int
    {
        $fullPath = $this->uploadsPath($relativePath);
        if (! is_file($fullPath)) {
            return null;
        }

        // FFprobe kontrolü — ShellExec helper ile 3 katmanlı guard
        // (function_exists + disable_functions + try/catch).
        if (! ShellExec::isAvailable() && ! ShellExec::isExecAvailable()) {
            return null;
        }

        $command = sprintf(
            'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
            escapeshellarg($fullPath),
        );

        $output = ShellExec::runAny($command);

        if ($output === null || trim($output) === '' || ! is_numeric(trim($output))) {
            return null;
        }

        return (int) round((float) $output);
    }

    /**
     * Video dosyası validation.
     *
     * @throws RuntimeException
     */
    private function validateVideo(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Video yükleme hatası: dosya geçersiz veya bozuk.');
        }

        if ($file->getSize() > self::MAX_VIDEO_SIZE_BYTES) {
            $maxMb = (int) (self::MAX_VIDEO_SIZE_BYTES / 1_048_576);
            throw new RuntimeException("Video maksimum {$maxMb} MB olabilir.");
        }

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_VIDEO_MIMES, true)) {
            throw new RuntimeException("Desteklenmeyen video formatı ({$mime}). Sadece MP4 veya MOV kabul edilir.");
        }
    }

    /**
     * Delete an image and all its responsive variants.
     */
    public function deleteImage(string $path): void
    {
        if (empty($path)) {
            return;
        }

        $fullPath = $this->uploadsPath($path);

        // Delete original
        $this->deleteFileIfExists($fullPath);

        // Delete all variants
        $info = pathinfo($fullPath);
        foreach (array_keys(self::SIZES) as $size) {
            $variantPath = "{$info['dirname']}/{$info['filename']}-{$size}.{$info['extension']}";
            $this->deleteFileIfExists($variantPath);
        }
    }

    /**
     * Delete a single file (non-image).
     */
    public function deleteFile(string $path): void
    {
        if (empty($path)) {
            return;
        }

        $this->deleteFileIfExists($this->uploadsPath($path));
    }

    /**
     * Replace an existing image: delete old + upload new.
     *
     * @return string New relative path
     */
    public function replaceImage(
        UploadedFile $file,
        string $folder,
        string $name,
        ?string $oldPath = null,
        ?array $sizes = null,
        ?int $maxWidth = null,
        ?int $maxHeight = null,
        bool $preserveFormat = false,
    ): string {
        if ($oldPath) {
            $this->deleteImage($oldPath);
        }

        return $this->uploadImage($file, $folder, $name, $sizes, $maxWidth, $maxHeight, $preserveFormat);
    }

    /**
     * Replace an existing file: delete old + upload new.
     *
     * @return string New relative path
     */
    public function replaceFile(
        UploadedFile $file,
        string $folder,
        string $name,
        ?string $oldPath = null,
    ): string {
        if ($oldPath) {
            $this->deleteFile($oldPath);
        }

        return $this->uploadFile($file, $folder, $name);
    }

    // ──────────────────────────────────────────────
    //  STATIC URL HELPERS
    // ──────────────────────────────────────────────

    /**
     * Get the public URL for an uploaded file.
     *
     * @param  string|null $path Relative path (e.g. "images/example-a1b2c3d4e5.webp")
     * @param  string|null $size Size variant (thumb, sm, md, lg) or null for original
     * @return string            Full URL path (e.g. "/uploads/images/example-a1b2c3d4e5-sm.webp")
     */
    public static function url(?string $path, ?string $size = null): string
    {
        if (empty($path)) {
            return self::placeholder($size);
        }

        // Normalize legacy paths to content
        if (str_starts_with($path, 'legacy/')) {
            $path = 'content/' . substr($path, 7);
        }

        if ($size !== null && isset(self::SIZES[$size])) {
            $info = pathinfo($path);
            $variantPath = "{$info['dirname']}/{$info['filename']}-{$size}.{$info['extension']}";

            // If variant exists, use it; otherwise fall back to original
            if (file_exists(self::basePath($variantPath))) {
                return "/uploads/{$variantPath}";
            }
        }

        return "/uploads/{$path}";
    }

    /**
     * Generate srcset attribute value for responsive images.
     *
     * @param  string|null       $path  Relative path
     * @param  array<string>|null $sizes Which sizes to include (null = sm, md, lg)
     * @return string                    srcset value (e.g. "/uploads/x-sm.webp 300w, ...")
     */
    public static function srcset(?string $path, ?array $sizes = null): string
    {
        if (empty($path)) {
            return '';
        }

        $sizes ??= ['sm', 'md', 'lg'];
        $parts = [];

        foreach ($sizes as $sizeName) {
            if (! isset(self::SIZES[$sizeName])) {
                continue;
            }

            $url = self::url($path, $sizeName);
            $width = self::SIZES[$sizeName]['width'];

            // Only include if variant file actually exists
            $info = pathinfo($path);
            $variantPath = "{$info['dirname']}/{$info['filename']}-{$sizeName}.{$info['extension']}";
            if (file_exists(self::basePath($variantPath))) {
                $parts[] = "{$url} {$width}w";
            }
        }

        // Add original as the largest option
        if (file_exists(self::basePath($path))) {
            $parts[] = "/uploads/{$path} " . self::getOriginalWidth($path) . 'w';
        }

        return implode(', ', $parts);
    }

    /**
     * Get available size definitions.
     *
     * @return array<string, array{width: int, height: ?int, crop: bool}>
     */
    public static function sizes(): array
    {
        return self::SIZES;
    }

    /**
     * Placeholder image URL when no image is available.
     */
    public static function placeholder(?string $size = null): string
    {
        $width = match ($size) {
            'thumb' => 150,
            'sm'    => 300,
            'md'    => 600,
            'lg'    => 1200,
            default => 600,
        };

        return "https://placehold.co/{$width}x{$width}/e8f5e9/2e7d32?text=Görsel+Yok";
    }

    /**
     * Kaynak görselden tam ölçüde, kare bir PNG üretir.
     *
     * PWA ikonları için var ve üç şartı birden karşılaması gerekiyor:
     * PNG (manifest'te WebP her platformda güvenli değil), kare, ve
     * manifest'te bildirilen ölçüyle birebir aynı — ölçü tutmazsa Chrome
     * kurulumu reddediyor.
     *
     * Görsel kareye sığdırılıyor, kırpılmıyor: logo bir kenarından kesilmiş
     * hâlde ana ekranda durmasın. Artan yer saydam bırakılıyor.
     */
    public function writeSquarePng(string $sourcePath, string $targetPath, int $size): bool
    {
        $info = @getimagesize($sourcePath);

        if ($info === false) {
            return false;
        }

        $source = match ($info['mime'] ?? '') {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png'  => @imagecreatefrompng($sourcePath),
            'image/gif'  => @imagecreatefromgif($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default      => false,
        };

        if ($source === false) {
            return false;
        }

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min($size / $sourceWidth, $size / $sourceHeight);
        $width = (int) round($sourceWidth * $scale);
        $height = (int) round($sourceHeight * $scale);

        imagecopyresampled(
            $canvas,
            $source,
            (int) (($size - $width) / 2),
            (int) (($size - $height) / 2),
            0,
            0,
            $width,
            $height,
            $sourceWidth,
            $sourceHeight,
        );

        $written = imagepng($canvas, $targetPath);

        imagedestroy($canvas);
        imagedestroy($source);

        return $written;
    }

    // ──────────────────────────────────────────────
    //  PRIVATE: IMAGE PROCESSING
    // ──────────────────────────────────────────────

    /**
     * Create a GD image resource from an uploaded file.
     */
    private function createGdImage(UploadedFile $file): \GdImage
    {
        $mime = $file->getMimeType();
        $path = $file->getPathname();

        $image = match ($mime) {
            'image/jpeg'        => imagecreatefromjpeg($path),
            'image/png'         => imagecreatefrompng($path),
            'image/gif'         => imagecreatefromgif($path),
            'image/webp'        => imagecreatefromwebp($path),
            'image/bmp'         => imagecreatefrombmp($path),
            default             => false,
        };

        if ($image === false) {
            throw new RuntimeException("Görsel okunamadı. Desteklenmeyen format: {$mime}");
        }

        // Preserve transparency for PNG/GIF/WebP
        if (in_array($mime, ['image/png', 'image/gif', 'image/webp'], true)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        return $image;
    }

    /**
     * Proportionally resize image to target width, keeping aspect ratio.
     */
    private function proportionalResize(
        \GdImage $source,
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
    ): \GdImage {
        $ratio = $sourceWidth / $sourceHeight;
        $targetHeight = (int) round($targetWidth / $ratio);

        return $this->resample($source, $sourceWidth, $sourceHeight, $targetWidth, $targetHeight);
    }

    /**
     * Fit image within a bounding box, preserving aspect ratio.
     */
    private function fitResize(
        \GdImage $source,
        int $sourceWidth,
        int $sourceHeight,
        int $maxWidth,
        int $maxHeight,
    ): \GdImage {
        $widthRatio = $maxWidth / $sourceWidth;
        $heightRatio = $maxHeight / $sourceHeight;
        $ratio = min($widthRatio, $heightRatio);

        $targetWidth = (int) round($sourceWidth * $ratio);
        $targetHeight = (int) round($sourceHeight * $ratio);

        return $this->resample($source, $sourceWidth, $sourceHeight, $targetWidth, $targetHeight);
    }

    /**
     * Crop from center and resize to exact dimensions.
     */
    private function cropResize(
        \GdImage $source,
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        int $targetHeight,
    ): \GdImage {
        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $targetWidth / $targetHeight;

        if ($sourceRatio > $targetRatio) {
            // Source is wider: crop from sides
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
        } else {
            // Source is taller: crop from top/bottom
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
        }

        $cropX = (int) round(($sourceWidth - $cropWidth) / 2);
        $cropY = (int) round(($sourceHeight - $cropHeight) / 2);

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            throw new RuntimeException('GD canvas oluşturulamadı.');
        }

        $this->prepareCanvas($canvas);

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            $cropX,
            $cropY,
            $targetWidth,
            $targetHeight,
            $cropWidth,
            $cropHeight,
        );

        return $canvas;
    }

    /**
     * Create a resampled copy of the image.
     */
    private function resample(
        \GdImage $source,
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        int $targetHeight,
    ): \GdImage {
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            throw new RuntimeException('GD canvas oluşturulamadı.');
        }

        $this->prepareCanvas($canvas);

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );

        return $canvas;
    }

    /**
     * Prepare canvas for transparency support.
     */
    private function prepareCanvas(\GdImage $canvas): void
    {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
    }

    /**
     * Save a GD image in its original format (PNG, JPEG, GIF, BMP).
     */
    private function saveInOriginalFormat(\GdImage $image, string $path, string $mime): void
    {
        $result = match ($mime) {
            'image/png' => imagepng($image, $path, 9),
            'image/jpeg' => imagejpeg($image, $path, 90),
            'image/gif' => imagegif($image, $path),
            'image/bmp' => imagebmp($image, $path),
            'image/webp' => imagewebp($image, $path, self::WEBP_QUALITY),
            default => throw new RuntimeException("Desteklenmeyen kayıt formatı: {$mime}"),
        };

        if ($result === false) {
            throw new RuntimeException("Dosya kaydedilemedi: {$path}");
        }
    }

    /**
     * Save a GD image as WebP.
     */
    private function saveAsWebp(\GdImage $image, string $path): void
    {
        if (! function_exists('imagewebp')) {
            throw new RuntimeException('GD kütüphanesinde WebP desteği bulunamadı.');
        }

        // Convert palette images to true color (required for WebP)
        if (! imageistruecolor($image)) {
            $width  = imagesx($image);
            $height = imagesy($image);
            $trueColor = imagecreatetruecolor($width, $height);
            imagealphablending($trueColor, false);
            imagesavealpha($trueColor, true);
            $transparent = imagecolorallocatealpha($trueColor, 0, 0, 0, 127);
            imagefill($trueColor, 0, 0, $transparent);
            imagecopy($trueColor, $image, 0, 0, 0, 0, $width, $height);
            imagedestroy($image);
            $image = $trueColor;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $result = imagewebp($image, $path, self::WEBP_QUALITY);

        if ($result === false) {
            throw new RuntimeException("WebP dosyası kaydedilemedi: {$path}");
        }
    }

    // ──────────────────────────────────────────────
    //  PRIVATE: FILESYSTEM HELPERS
    // ──────────────────────────────────────────────

    /**
     * Validate uploaded file is an acceptable image.
     */
    private function validateImage(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Dosya yükleme hatası: ' . $file->getErrorMessage());
        }

        $mime = $file->getMimeType();

        if (! in_array($mime, self::ALLOWED_IMAGE_MIMES, true)) {
            throw new RuntimeException(
                "Desteklenmeyen görsel formatı: {$mime}. " .
                'Kabul edilen formatlar: JPEG, PNG, GIF, WebP, BMP'
            );
        }
    }

    /**
     * Get the full filesystem path for uploads.
     */
    /**
     * Root of the upload directory.
     *
     * Read from config so the test suite can redirect writes somewhere
     * disposable instead of the real public/uploads folder.
     *
     * Static because the URL helpers below have to resolve against the same
     * root the writes use; when they disagreed, a configured upload path meant
     * every variant lookup silently missed and fell back to the original.
     */
    public static function basePath(string $relativePath = ''): string
    {
        $base = rtrim((string) config('uploads.path', public_path('uploads')), '/');

        return $relativePath !== '' ? "{$base}/{$relativePath}" : $base;
    }

    private function uploadsPath(string $relativePath = ''): string
    {
        return self::basePath($relativePath);
    }

    /**
     * Ensure a directory exists, creating it recursively if needed.
     */
    private function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Delete a file if it exists.
     */
    private function deleteFileIfExists(string $path): void
    {
        if (file_exists($path) && is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Get the width of the original uploaded image.
     */
    private static function getOriginalWidth(string $path): int
    {
        $fullPath = self::basePath($path);

        if (! file_exists($fullPath)) {
            return 1920;
        }

        $size = getimagesize($fullPath);

        return $size !== false ? $size[0] : 1920;
    }

    /**
     * Sunucunun gerçekte kabul ettiği yükleme sınırları.
     *
     * Arayüzdeki "en fazla N MB" metni buradan üretilir. php.ini 2 MB derken
     * ekranın 10 MB vaat etmesi kullanıcıyı baştan kaybedeceği bir yüklemeye
     * sokar: post_max_size aşıldığında PHP gövdeyi komple atar, CSRF alanı da
     * onunla gittiği için istek 419 döner ve doldurulmuş form kaybolur —
     * FormRequest'teki nazik hata mesajı çalışma fırsatı bile bulamaz.
     *
     * Uygulamanın kendi tavanı ile sunucunun tavanından hangisi düşükse o
     * geçerlidir; sunucuyu yukarı zorlamak paylaşımlı hosting'de mümkün değil.
     *
     * @param  int $appMaxPerFile Uygulamanın dosya başına tavanı (bayt)
     * @param  int $appMaxFiles   Uygulamanın dosya sayısı tavanı
     * @return array{per_file: int, post_max: int, max_files: int}
     */
    public function limits(int $appMaxPerFile, int $appMaxFiles): array
    {
        $uploadMax = self::iniBytes('upload_max_filesize');
        $postMax = self::iniBytes('post_max_size');
        $iniFiles = (int) ini_get('max_file_uploads');

        return [
            'per_file'  => self::lowestPositive([$appMaxPerFile, $uploadMax, $postMax], $appMaxPerFile),
            'post_max'  => $postMax > 0 ? $postMax : $appMaxPerFile,
            'max_files' => self::lowestPositive([$appMaxFiles, $iniFiles], $appMaxFiles),
        ];
    }

    /**
     * Adaylardan pozitif olanların en küçüğü; hiçbiri pozitif değilse yedek
     * değer.
     *
     * Öncesinde `min(...array_filter([...]))` yazıyordu. `array_filter`
     * sıfırları atıyor, yani tüm adaylar sıfır olduğunda `min()` hiç
     * argümansız çağrılıyor ve **ölümcül hata** veriyordu. Bugünkü çağıranlar
     * pozitif sabitler geçiyor, ama sıfır geçen ilk çağrı yükleme yolunu
     * çökertirdi. Sıfırın "sınır yok" demek olduğu niyeti de artık okunuyor.
     *
     * @param list<int> $candidates
     */
    private static function lowestPositive(array $candidates, int $fallback): int
    {
        $positive = array_values(array_filter($candidates, static fn (int $value): bool => $value > 0));

        return $positive === [] ? $fallback : min($positive);
    }

    /**
     * "8M", "512K", "1G" gibi ini kısaltmalarını bayta çevirir.
     *
     * Sınırsızı (0 ya da boş) PHP_INT_MAX sayar; min() ile karşılaştırıldığında
     * sınırsız bir değerin diğerlerini bastırmaması için.
     */
    private static function iniBytes(string $key): int
    {
        $raw = trim((string) ini_get($key));

        if ($raw === '' || $raw === '0' || $raw === '-1') {
            return PHP_INT_MAX;
        }

        $value = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g'     => $value * 1024 * 1024 * 1024,
            'm'     => $value * 1024 * 1024,
            'k'     => $value * 1024,
            default => $value,
        };
    }
}
