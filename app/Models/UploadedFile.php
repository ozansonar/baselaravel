<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * FileManager varlık modeli — admin elle yüklediği dosyaları temsil eder.
 *
 * `category` MIME prefix'inden otomatik belirlenir (FileManagerService::resolveCategory).
 * Görseller için `width`/`height` saklanır; diğer dosya türleri için null.
 *
 * Public URL: `upload_url($stored_path)` veya `asset('uploads/' . $stored_path)`.
 * Soft delete edilen kayıtların dosyası fiziksel olarak silinir
 * (FileManagerService::delete) — restore edilse bile dosya yolu geçersiz olur.
 */
final class UploadedFile extends Model
{
    use HasFactory, SoftDeletes;

    /** @var string */
    protected $table = 'uploaded_files';

    public const string CATEGORY_IMAGE    = 'image';
    public const string CATEGORY_DOCUMENT = 'document';
    public const string CATEGORY_VIDEO    = 'video';
    public const string CATEGORY_AUDIO    = 'audio';
    public const string CATEGORY_ARCHIVE  = 'archive';
    public const string CATEGORY_OTHER    = 'other';

    /** @var list<string> */
    protected $fillable = [
        'original_name',
        'stored_path',
        'original_path',
        'mime_type',
        'extension',
        'file_size',
        'webp_size',
        'category',
        'title',
        'alt_text',
        'hash',
        'width',
        'height',
        'download_count',
        'is_public',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size'      => 'integer',
            'webp_size'      => 'integer',
            'width'          => 'integer',
            'height'         => 'integer',
            'download_count' => 'integer',
            'is_public'      => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return $this->category === self::CATEGORY_IMAGE;
    }

    public function isDocument(): bool
    {
        return $this->category === self::CATEGORY_DOCUMENT;
    }

    public function isVideo(): bool
    {
        return $this->category === self::CATEGORY_VIDEO;
    }

    /**
     * Public erişilebilir URL (relative) — `/uploads/...`.
     * Blog/sayfa içine kopyalanır. Görselse stored_path = WebP.
     */
    public function publicUrl(): string
    {
        return upload_url($this->stored_path);
    }

    /**
     * Public erişilebilir URL (full) — `https://site.com/uploads/...`.
     * Site adresi dahil, dış paylaşım için (e-posta, sosyal medya).
     */
    public function fullUrl(): string
    {
        return url($this->publicUrl());
    }

    /**
     * Orijinal dosya URL (relative) — sadece görseller için JPG/PNG.
     * Görsel değilse stored_path zaten orijinaldir, fallback olarak onu döner.
     */
    public function originalUrl(): string
    {
        if (! $this->isImage() || $this->original_path === null || $this->original_path === '') {
            return upload_url($this->stored_path);
        }

        return upload_url($this->original_path);
    }

    /**
     * Orijinal URL (full) — site adresi dahil.
     */
    public function originalFullUrl(): string
    {
        return url($this->originalUrl());
    }

    /**
     * Görseller için thumbnail URL (UploadService variant).
     * Görsel değilse boş string döner.
     */
    public function thumbnailUrl(): string
    {
        if (! $this->isImage()) {
            return '';
        }

        return upload_url($this->stored_path, 'thumb');
    }

    /**
     * Bytes → "2.3 MB" formatı (genel helper).
     */
    public static function formatBytes(?int $bytes): string
    {
        if ($bytes === null || $bytes <= 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $size : number_format($size, 1, ',', '.')) . ' ' . $units[$i];
    }

    /**
     * Yüklenen dosyanın orijinal boyutu — "2.3 MB".
     */
    public function humanSize(): string
    {
        return self::formatBytes($this->file_size);
    }

    /**
     * WebP'ye çevrilmiş boyut (sadece görsel) — "1.1 MB".
     * Görsel değilse veya WebP boyutu kayıtlı değilse "—".
     */
    public function webpSizeHuman(): string
    {
        if (! $this->isImage()) {
            return '—';
        }

        return self::formatBytes($this->webp_size);
    }

    /**
     * Orijinal vs WebP tasarruf yüzdesi (sadece görsel + iki boyut da varsa).
     * Negatif olabilir (WebP daha büyükse — nadir durum, küçük dosyalarda).
     */
    public function webpSavingsPercent(): ?int
    {
        if (! $this->isImage() || ! $this->file_size || ! $this->webp_size) {
            return null;
        }

        $savings = $this->file_size - $this->webp_size;

        return (int) round(($savings / $this->file_size) * 100);
    }

    /**
     * Bootstrap Icons class adı — dosya tipi ikonları.
     */
    public function iconClass(): string
    {
        return match (strtolower($this->extension)) {
            'pdf'                       => 'bi-file-earmark-pdf',
            'doc', 'docx'               => 'bi-file-earmark-word',
            'xls', 'xlsx', 'csv'        => 'bi-file-earmark-excel',
            'ppt', 'pptx'               => 'bi-file-earmark-slides',
            'zip', 'rar', '7z', 'tar', 'gz' => 'bi-file-earmark-zip',
            'mp4', 'mov', 'avi', 'webm' => 'bi-camera-reels',
            'mp3', 'wav', 'ogg', 'm4a'  => 'bi-music-note-beamed',
            'txt'                       => 'bi-file-earmark-text',
            'jpg', 'jpeg', 'png', 'webp', 'gif' => 'bi-file-earmark-image',
            default                     => 'bi-file-earmark',
        };
    }

    /**
     * Kategori bazlı renk class'ı (Bootstrap text-* utilities).
     */
    public function iconColorClass(): string
    {
        return match ($this->category) {
            self::CATEGORY_IMAGE    => 'text-primary',
            self::CATEGORY_DOCUMENT => match (strtolower($this->extension)) {
                'pdf'        => 'text-danger',
                'doc', 'docx' => 'text-info',
                'xls', 'xlsx', 'csv' => 'text-success',
                default      => 'text-warning',
            },
            self::CATEGORY_VIDEO    => 'text-purple',
            self::CATEGORY_AUDIO    => 'text-warning',
            self::CATEGORY_ARCHIVE  => 'text-secondary',
            default                 => 'text-muted',
        };
    }

    /**
     * Türkçe kategori etiketi (UI badge için).
     */
    public function categoryLabel(): string
    {
        return match ($this->category) {
            self::CATEGORY_IMAGE    => 'Görsel',
            self::CATEGORY_DOCUMENT => 'Belge',
            self::CATEGORY_VIDEO    => 'Video',
            self::CATEGORY_AUDIO    => 'Ses',
            self::CATEGORY_ARCHIVE  => 'Arşiv',
            default                 => 'Diğer',
        };
    }

    /**
     * İndirme sayacını artır — public download endpoint'inden çağrılır.
     */
    public function markDownloaded(): void
    {
        $this->increment('download_count');
    }

    // ── Scopes ──

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Dosya adı + title + alt_text üzerinde arama.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        return $query->where(static function (Builder $q) use ($like): void {
            $q->where('original_name', 'like', $like)
              ->orWhere('title', 'like', $like)
              ->orWhere('alt_text', 'like', $like);
        });
    }
}
