<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UploadedFile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FileManager orchestrator'ü — admin elle yüklediği dosyaların yönetimi.
 *
 * Özellikler:
 *   - SHA256 duplicate kontrolü (aynı içerik 2 kez yüklenmez)
 *   - MIME prefix → kategori otomatik sınıflandırma
 *   - Görsel için width/height otomatik probe
 *   - DB::transaction (filesystem + DB satırı atomik)
 *   - Stats cache (5 dk) + invalidation
 *
 * Tüm filesystem operasyonları UploadService üzerinden — ham `Storage::disk`
 * çağrısı YOK (CLAUDE.md kuralı).
 */
final class FileManagerService
{
    private const string UPLOAD_FOLDER = 'files';

    private const string STATS_CACHE_KEY = 'file_manager.stats';

    private const int STATS_CACHE_TTL = 300; // 5 dk

    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    /**
     * Dosya yükle — atomik filesystem + DB.
     *
     * @return array{
     *     file: UploadedFile,
     *     duplicate: bool,
     * }
     */
    public function upload(
        HttpUploadedFile $file,
        ?string $title = null,
        ?string $altText = null,
        ?int $userId = null,
    ): array {
        // 1. SHA256 hash — duplicate kontrolü için
        $hash = (string) hash_file('sha256', $file->getRealPath());

        $existing = UploadedFile::query()->where('hash', $hash)->first();
        if ($existing !== null) {
            return ['file' => $existing, 'duplicate' => true];
        }

        // 2. Filesystem + DB atomik
        $entity = DB::transaction(function () use ($file, $title, $altText, $userId, $hash): UploadedFile {
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $mime = (string) ($file->getMimeType() ?? 'application/octet-stream');
            $category = $this->resolveCategory($mime, $extension);
            $baseName = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
            $originalSize = (int) $file->getSize();

            // Görsel ise:
            //   1. Orijinal JPG/PNG → preserveFormat=true (kullanıcı orijinali da kopyalayabilsin)
            //   2. Aynı dosyayı WebP'ye çevirip variants üret (ana stored_path)
            // Görsel değilse:
            //   - Sadece orijinal — uploadFile (PDF/Word/Excel WebP'ye çevrilmez)
            $originalPath = null;
            $storedPath = null;
            $webpSize = null;

            if ($category === UploadedFile::CATEGORY_IMAGE) {
                // Orijinal (preserveFormat=true → JPG/PNG/etc. korunur, variants yok)
                $originalPath = $this->uploadService->uploadImage(
                    $file,
                    self::UPLOAD_FOLDER,
                    $baseName . '-original',
                    sizes: [],            // variants gerekmez
                    preserveFormat: true,
                );

                // WebP versiyonu (variants dahil)
                $storedPath = $this->uploadService->uploadImage(
                    $file,
                    self::UPLOAD_FOLDER,
                    $baseName,
                );

                // WebP dosya boyutu probe
                $webpAbs = UploadService::basePath($storedPath);
                if (is_file($webpAbs)) {
                    $webpSize = (int) filesize($webpAbs);
                }
            } else {
                $storedPath = $this->uploadService->uploadFile(
                    $file,
                    self::UPLOAD_FOLDER,
                    $baseName,
                );
            }

            // 3. Görsel için boyut probe
            $width = null;
            $height = null;
            if ($category === UploadedFile::CATEGORY_IMAGE) {
                $absolute = UploadService::basePath($storedPath);
                $info = @getimagesize($absolute);
                if ($info !== false) {
                    $width = (int) $info[0];
                    $height = (int) $info[1];
                }
            }

            // 4. DB satırı — extension'ı stored path'in son uzantısından al
            //    (UploadService bazı durumlarda WebP'ye çevirebilir, takip edelim)
            $finalExtension = strtolower((string) pathinfo($storedPath, PATHINFO_EXTENSION)) ?: $extension;

            $uploaded = UploadedFile::create([
                'original_name' => (string) $file->getClientOriginalName(),
                'stored_path'   => $storedPath,
                'original_path' => $originalPath,
                'mime_type'     => $mime,
                'extension'     => $finalExtension,
                'file_size'     => $originalSize,    // yüklenen orijinal bytes
                'webp_size'     => $webpSize,        // WebP'ye çevrilmiş bytes (görsel)
                'category'      => $category,
                'title'         => $title,
                'alt_text'      => $altText,
                'hash'          => $hash,
                'width'         => $width,
                'height'        => $height,
                'is_public'     => true,
                'uploaded_by'   => $userId,
            ]);

            Cache::forget(self::STATS_CACHE_KEY);

            return $uploaded;
        });

        return ['file' => $entity, 'duplicate' => false];
    }

    /**
     * Dosyayı sil — filesystem'den fiziksel + DB soft delete.
     */
    public function delete(UploadedFile $uploaded): void
    {
        DB::transaction(function () use ($uploaded): void {
            // 1. Stored (WebP) dosyayı sil — görsel ise variants dahil
            if ($uploaded->stored_path !== '') {
                try {
                    if ($uploaded->isImage()) {
                        $this->uploadService->deleteImage($uploaded->stored_path);
                    } else {
                        $this->uploadService->deleteFile($uploaded->stored_path);
                    }
                } catch (\Throwable $e) {
                    Log::warning('FileManagerService::delete — stored dosya silinemedi', [
                        'file_id' => $uploaded->id,
                        'path'    => $uploaded->stored_path,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            // 2. Orijinal dosyayı da sil (sadece görsel için ayrı tutulur)
            if ($uploaded->original_path !== null && $uploaded->original_path !== '') {
                try {
                    // Orijinal preserveFormat=true ile yüklendi — variants yok,
                    // tek dosya, deleteFile yeterli
                    $this->uploadService->deleteFile($uploaded->original_path);
                } catch (\Throwable $e) {
                    Log::warning('FileManagerService::delete — orijinal dosya silinemedi', [
                        'file_id' => $uploaded->id,
                        'path'    => $uploaded->original_path,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            $uploaded->delete();

            Cache::forget(self::STATS_CACHE_KEY);
        });
    }

    /**
     * Filtrelenebilir liste.
     *
     * @param  array{q?: string, category?: string, date_from?: string, date_to?: string}  $filters
     */
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $query = UploadedFile::query()->with('uploader')->latest('id');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Kategori bazlı sayım + toplam boyut.
     *
     * @return array{
     *     total_files: int,
     *     total_size: int,
     *     this_month: int,
     *     by_category: array<string, int>,
     * }
     */
    public function stats(): array
    {
        return Cache::remember(self::STATS_CACHE_KEY, self::STATS_CACHE_TTL, static function (): array {
            $rows = UploadedFile::query()
                ->selectRaw('category, COUNT(*) as cnt, COALESCE(SUM(file_size), 0) as size')
                ->groupBy('category')
                ->get();

            $byCategory = [];
            $totalFiles = 0;
            $totalSize = 0;

            foreach ($rows as $row) {
                $byCategory[$row->category] = (int) $row->cnt;
                $totalFiles += (int) $row->cnt;
                $totalSize += (int) $row->size;
            }

            $thisMonth = UploadedFile::query()
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();

            return [
                'total_files' => $totalFiles,
                'total_size'  => $totalSize,
                'this_month'  => $thisMonth,
                'by_category' => $byCategory,
            ];
        });
    }

    /**
     * MIME → kategori sınıflandırma.
     */
    private function resolveCategory(string $mime, string $extension): string
    {
        $mime = strtolower($mime);
        $ext = strtolower($extension);

        if (str_starts_with($mime, 'image/')) {
            return UploadedFile::CATEGORY_IMAGE;
        }
        if (str_starts_with($mime, 'video/')) {
            return UploadedFile::CATEGORY_VIDEO;
        }
        if (str_starts_with($mime, 'audio/')) {
            return UploadedFile::CATEGORY_AUDIO;
        }

        $documentExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt'];
        if (in_array($ext, $documentExts, true)) {
            return UploadedFile::CATEGORY_DOCUMENT;
        }

        $archiveExts = ['zip', 'rar', '7z', 'tar', 'gz'];
        if (in_array($ext, $archiveExts, true)) {
            return UploadedFile::CATEGORY_ARCHIVE;
        }

        return UploadedFile::CATEGORY_OTHER;
    }

    /**
     * @param  Builder<UploadedFile>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['q'])) {
            $query->search((string) $filters['q']);
        }

        if (! empty($filters['category'])) {
            $query->ofCategory((string) $filters['category']);
        }

        if (! empty($filters['date_from'])) {
            try {
                $query->where('created_at', '>=', \Carbon\Carbon::parse((string) $filters['date_from'])->startOfDay());
            } catch (\Throwable) {
                // ignore malformed
            }
        }

        if (! empty($filters['date_to'])) {
            try {
                $query->where('created_at', '<=', \Carbon\Carbon::parse((string) $filters['date_to'])->endOfDay());
            } catch (\Throwable) {
                // ignore
            }
        }
    }
}
