<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bir içeriğin bir dilindeki kayıtlı hâli.
 *
 * Yumuşak silme trait'i projedeki kurala uyuyor ama sürümlerin **budanması**
 * bilerek kalıcı ({@see \App\Services\ContentRevisionService::prune()}):
 * tavanın var olma sebebi disk, ve yumuşak silinen satır diskte durmaya devam
 * ederdi. Yumuşak silme, tek bir sürümü elle gizlemek gerekirse duruyor.
 */
class ContentRevision extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'revisionable_type',
        'revisionable_id',
        'locale',
        'user_id',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Kaydeden kişi; hesabı silinmişse null. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Bir içeriğin bir dilindeki sürümleri — yeniden eskiye.
     *
     * @param  Builder<static> $query
     * @return Builder<static>
     */
    public function scopeForTarget(Builder $query, Model $target): Builder
    {
        return $query
            ->where('revisionable_type', $target->getMorphClass())
            ->where('revisionable_id', $target->getKey())
            ->where('locale', (string) $target->getAttribute('locale'))
            ->latest('id');
    }

    /**
     * Sürümdeki bir alanın değeri.
     *
     * Alan sonradan eklendiyse eski sürümde yok; ekranın bunu boş göstermesi
     * gerekiyor, hata vermesi değil.
     */
    public function value(string $field): mixed
    {
        return $this->payload[$field] ?? null;
    }

    /**
     * Ekranda satırı tanıtan başlık.
     *
     * Başlık o sürümde neydi diye bakılıyor: bugünkü başlığı göstermek,
     * "başlığı düzelttim" diyen bir sürümü ayırt edilemez yapardı.
     */
    public function label(): string
    {
        $title = $this->value('title');

        return is_string($title) && trim($title) !== ''
            ? $title
            : __('Başlıksız');
    }
}
