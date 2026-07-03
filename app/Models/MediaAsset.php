<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Görsel Kütüphanesi (MediaLibrary) varlık modeli.
 *
 * Her satır bir görseli ve onun ürün eşleşmesini temsil eder. Cron blog
 * yazarken hangi ürünü seçtiyse o ürünün görsellerinden birini, ürün
 * eşleşmesi yoksa "Genel" (product_name=null) havuzdan rastgele seçer.
 *
 * Görsel formatı: 1:1 kare 1500×1500 (validation seviyesinde zorunlu).
 *
 * Smart selection için iki sayaç:
 *   - usage_count      → kaç blog'da kullanıldığı (LFU — least frequently used)
 *   - last_used_at     → son kullanım zamanı       (LRU — least recently used)
 *
 * Cron `pickFor()` çağrısında bu ikisinin kombinasyonu kullanılır:
 *   ORDER BY last_used_at ASC NULLS FIRST, usage_count ASC
 *   LIMIT 5  → top 5'ten PHP tarafında rastgele bir tanesi
 */
final class MediaAsset extends Model
{
    use HasFactory, SoftDeletes;

    /** @var string */
    protected $table = 'media_assets';

    /** @var list<string> */
    protected $fillable = [
        'product_name',
        'title',
        'image_path',
        'width',
        'height',
        'usage_count',
        'last_used_at',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'width'        => 'integer',
            'height'       => 'integer',
            'usage_count'  => 'integer',
            'sort_order'   => 'integer',
            'is_active'    => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Görseli yükleyen admin (referans amaçlı).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Cron seçtiğinde tetiklenir — usage_count++ + last_used_at = now().
     * Smart selection algoritmasının LRU + LFU sıralamasını besler.
     */
    public function markUsed(): void
    {
        $this->update([
            'usage_count'  => $this->usage_count + 1,
            'last_used_at' => now(),
        ]);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Belirli bir ürünün görselleri.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForProduct(Builder $query, string $productName): Builder
    {
        return $query->where('product_name', $productName);
    }

    /**
     * "Genel" havuz — ürün-bağımsız fallback görselleri.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeGeneral(Builder $query): Builder
    {
        return $query->whereNull('product_name');
    }

    /**
     * Smart selection sıralaması: önce hiç kullanılmamış, sonra LRU + LFU.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLeastUsed(Builder $query): Builder
    {
        return $query
            ->orderByRaw('last_used_at IS NULL DESC') // NULLS FIRST emulation (MySQL)
            ->orderBy('last_used_at', 'asc')
            ->orderBy('usage_count', 'asc');
    }
}
