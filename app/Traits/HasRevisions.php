<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\ContentRevision;
use App\Services\ContentRevisionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * İçeriğin geçmişini tutar.
 *
 * Hangi alanların sürümlendiği burada değil `config/revisions.php`'de: aynı
 * soru iki yerde cevaplansaydı biri ötekinden sapardı ve sapma, ancak birisi
 * geri yükleyip kaybolan alanı fark ettiğinde görünürdü.
 *
 * Kanca `saved` üzerinde — hem yeni kayıtta hem güncellemede. Sürüm yalnız
 * sürümlenen alanlardan biri değiştiğinde yazılıyor; kararı servis veriyor.
 *
 * @see \App\Services\ContentRevisionService
 */
trait HasRevisions
{
    public static function bootHasRevisions(): void
    {
        static::saved(static function (Model $model): void {
            app(ContentRevisionService::class)->capture($model);
        });

        // İçerik kalıcı olarak silindiğinde geçmişi de gidiyor: sahibi olmayan
        // bir sürüm hiçbir yerden ulaşılamaz ama tabloda yer kaplar. Yumuşak
        // silmede geçmiş duruyor — geri alınan içerik geçmişiyle birlikte
        // dönmeli.
        static::forceDeleted(static function (Model $model): void {
            ContentRevision::query()
                ->where('revisionable_type', $model->getMorphClass())
                ->where('revisionable_id', $model->getKey())
                ->forceDelete();
        });
    }

    /**
     * Bu satırın (yani bu dilin) sürümleri — yeniden eskiye.
     *
     * @return MorphMany<ContentRevision, $this>
     */
    public function revisions(): MorphMany
    {
        return $this->morphMany(ContentRevision::class, 'revisionable')
            ->where('locale', (string) $this->getAttribute('locale'))
            ->latest('id');
    }
}
