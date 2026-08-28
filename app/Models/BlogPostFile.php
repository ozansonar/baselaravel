<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FileKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bir içeriğe iliştirilen tek dosya.
 *
 * Kayıt dosyanın adresini taşır; dosyanın kendisi public/uploads altındadır.
 * Ek, dil grubuna değil o dilin blog_posts satırına bağlıdır — çeviriler ayrı
 * satır olduğu için ekler de kendiliğinden dil bazlı olur.
 */
class BlogPostFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'blog_post_id',
        'token',
        'user_id',
        'path',
        'original_name',
        'extension',
        'mime_type',
        'size',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'blog_post_id' => 'integer',
            'user_id'      => 'integer',
            'size'         => 'integer',
            'sort_order'   => 'integer',
        ];
    }

    // ── Relationships ──

    /**
     * @return BelongsTo<BlogPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Scopes ──

    /**
     * Henüz bir içeriğe bağlanmamış, kaydedilmeyi bekleyen yüklemeler.
     *
     * Sahibiyle birlikte aranıyor: belirteç uydurulsa bile başkasının dosyası
     * iliştirilemez.
     *
     * @param  Builder<static> $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query, ?int $userId): Builder
    {
        $query->whereNull('blog_post_id');

        return $userId === null
            ? $query->whereNull('user_id')
            : $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<static> $query
     * @return Builder<static>
     */
    public function scopeSorted(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    // ── Accessors ──

    public function kind(): FileKind
    {
        return FileKind::fromExtension($this->extension);
    }

    public function isImage(): bool
    {
        return $this->kind() === FileKind::Image;
    }

    /** Dosyanın doğrudan adresi — görsel önizlemeleri bunu kullanır. */
    public function url(): string
    {
        return upload_url($this->path);
    }

    /** İndirme adresi: dosya kullanıcıya yüklediği adla iner. */
    public function downloadUrl(): string
    {
        return route('blog.files.download', $this);
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;

        return match (true) {
            $bytes >= 1_073_741_824 => round($bytes / 1_073_741_824, 1) . ' GB',
            $bytes >= 1_048_576     => round($bytes / 1_048_576, 1) . ' MB',
            $bytes >= 1024          => round($bytes / 1024) . ' KB',
            default                 => $bytes . ' B',
        };
    }
}
