<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MediaLibraryImage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Görsel kütüphanesini ZIP olarak export/import eder.
 *
 * Export: tüm görselleri + manifest.json (metadata) ZIP'e koyar
 * Import: ZIP'i parse eder, manifest'ten görselleri DB'ye + diske yazar
 *
 * Kullanım: backup, başka kuruluma taşıma, lokal yedek.
 */
final class ImageLibraryExporter
{
    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    /**
     * Tüm kütüphaneyi ZIP olarak export et. Path döner (caller stream eder).
     *
     * @throws \RuntimeException
     */
    public function export(): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'il_export_');
        if ($tmpPath === false) {
            throw new \RuntimeException('Geçici dosya oluşturulamadı.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('ZIP açılamadı: ' . $tmpPath);
        }

        $images = MediaLibraryImage::all();
        $manifest = [
            'version'     => 1,
            'exported_at' => Carbon::now()->toIso8601String(),
            'total'       => $images->count(),
            'items'       => [],
        ];

        foreach ($images as $img) {
            $imgAbs = public_path('uploads/' . $img->image_path);
            if (is_file($imgAbs)) {
                $zip->addFile($imgAbs, 'images/' . basename($img->image_path));
            }

            if ($img->is_video && $img->video_path) {
                $vidAbs = public_path('uploads/' . $img->video_path);
                if (is_file($vidAbs)) {
                    $zip->addFile($vidAbs, 'videos/' . basename($img->video_path));
                }
            }

            $manifest['items'][] = [
                'id'                     => $img->id,
                'image_basename'         => basename($img->image_path),
                'video_basename'         => $img->video_path ? basename($img->video_path) : null,
                'is_video'               => (bool) $img->is_video,
                'video_duration_seconds' => $img->video_duration_seconds,
                'media_type'             => $img->media_type,
                'tags'                   => $img->tags,
                'theme'                  => $img->theme,
                'mood'                   => $img->mood,
                'season'                 => $img->season,
                'description'            => $img->description,
                'alt_text'               => $img->alt_text,
                'visibility'             => $img->visibility ?? 'private',
                'usage_count'            => $img->usage_count,
                'last_used_at'           => $img->last_used_at?->toIso8601String(),
                'created_at'             => $img->created_at?->toIso8601String(),
            ];
        }

        $zip->addFromString('manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        Log::info('ImageLibraryExporter: export tamamlandı', [
            'path'  => $tmpPath,
            'total' => $images->count(),
            'size'  => filesize($tmpPath),
        ]);

        return $tmpPath;
    }

    /**
     * ZIP'ten import et. Manifest'i okur, dosyaları diske + DB'ye yazar.
     *
     * Idempotent: aynı image_path ile DB'de kayıt varsa atlar (skip).
     *
     * @return array{success: bool, imported: int, skipped: int, message: string}
     */
    public function import(string $zipAbsolutePath): array
    {
        if (! is_file($zipAbsolutePath)) {
            return ['success' => false, 'imported' => 0, 'skipped' => 0, 'message' => 'ZIP bulunamadı.'];
        }

        $zip = new ZipArchive();
        if ($zip->open($zipAbsolutePath) !== true) {
            return ['success' => false, 'imported' => 0, 'skipped' => 0, 'message' => 'ZIP açılamadı.'];
        }

        $manifestRaw = $zip->getFromName('manifest.json');
        if ($manifestRaw === false) {
            $zip->close();
            return ['success' => false, 'imported' => 0, 'skipped' => 0, 'message' => 'manifest.json bulunamadı (geçerli kütüphane export ZIP\'i değil).'];
        }

        try {
            /** @var array<string, mixed> $manifest */
            $manifest = json_decode($manifestRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $zip->close();
            return ['success' => false, 'imported' => 0, 'skipped' => 0, 'message' => 'manifest.json parse hatası: ' . $e->getMessage()];
        }

        if (! isset($manifest['items']) || ! is_array($manifest['items'])) {
            $zip->close();
            return ['success' => false, 'imported' => 0, 'skipped' => 0, 'message' => 'manifest.json içinde items dizisi yok.'];
        }

        $targetDir = $this->ensureUploadDir('image-library/imported-' . now()->format('Ymd-His'));
        $imported = 0;
        $skipped  = 0;

        foreach ($manifest['items'] as $item) {
            if (! is_array($item)) continue;

            $imgBase = (string) ($item['image_basename'] ?? '');
            if ($imgBase === '') continue;

            // ZIP'ten görseli çıkar
            $imgContent = $zip->getFromName('images/' . $imgBase);
            if ($imgContent === false) continue;

            $newImgRelative = $this->writeContentToDisk($targetDir, $imgBase, $imgContent);

            // Aynı image_path varsa atla
            if (MediaLibraryImage::where('image_path', $newImgRelative)->exists()) {
                $skipped++;
                continue;
            }

            // Video varsa onu da çıkar
            $newVideoRelative = null;
            if (! empty($item['video_basename'])) {
                $vidContent = $zip->getFromName('videos/' . $item['video_basename']);
                if ($vidContent !== false) {
                    $newVideoRelative = $this->writeContentToDisk($targetDir, $item['video_basename'], $vidContent);
                }
            }

            MediaLibraryImage::create([
                'image_path'             => $newImgRelative,
                'video_path'             => $newVideoRelative,
                'is_video'               => (bool) ($item['is_video'] ?? false),
                'video_duration_seconds' => $item['video_duration_seconds'] ?? null,
                'media_type'             => $item['media_type'] ?? 'feed',
                'tags'                   => $item['tags']  ?? null,
                'theme'                  => $item['theme'] ?? null,
                'mood'                   => $item['mood']  ?? null,
                'season'                 => $item['season']?? null,
                'description'            => $item['description'] ?? null,
                'alt_text'               => $item['alt_text']    ?? null,
                'visibility'             => $item['visibility']  ?? 'private',
                'usage_count'            => 0, // import'ta sıfırla
                'last_used_at'           => null,
            ]);

            $imported++;
        }

        $zip->close();

        return [
            'success'  => true,
            'imported' => $imported,
            'skipped'  => $skipped,
            'message'  => "Import tamamlandı: {$imported} eklendi, {$skipped} atlandı (zaten vardı).",
        ];
    }

    private function ensureUploadDir(string $relative): string
    {
        $abs = public_path('uploads/' . $relative);
        if (! is_dir($abs)) {
            @mkdir($abs, 0775, true);
        }
        return $relative;
    }

    private function writeContentToDisk(string $relativeDir, string $basename, string $content): string
    {
        $absDir = public_path('uploads/' . $relativeDir);
        if (! is_dir($absDir)) @mkdir($absDir, 0775, true);

        $newName = uniqid('imp-', false) . '-' . $basename;
        $absPath = $absDir . DIRECTORY_SEPARATOR . $newName;
        file_put_contents($absPath, $content);

        return $relativeDir . '/' . $newName;
    }
}
