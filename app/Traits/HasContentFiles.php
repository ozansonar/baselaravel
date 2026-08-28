<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\ContentFile;
use App\Services\ContentFileService;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Ek taşıyabilen içerik.
 *
 * Çeviriler ayrı satır olduğu için ek de o dile ait: Türkçe sürümün kırk eki
 * varken İngilizcesinin hiç eki olmayabilir.
 *
 * Silme davranışı yabancı anahtarda değil burada: yumuşak silinen içerik
 * eklerini de gizlemeli, geri alındığında birlikte dönmeli. Modelin gözlemcisi
 * bu üç metodu çağırıyor.
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait HasContentFiles
{
    /**
     * @return MorphMany<ContentFile, $this>
     */
    public function files(): MorphMany
    {
        return $this->morphMany(ContentFile::class, 'attachable')->sorted();
    }

    /**
     * İçerik yumuşak silindi: ekleri de gizleniyor.
     */
    public function softDeleteFiles(): void
    {
        $this->files()->each(fn (ContentFile $file) => $file->delete());
    }

    /**
     * İçerik geri alındı: ekleri de dönüyor.
     */
    public function restoreFiles(): void
    {
        $this->files()->onlyTrashed()->each(fn (ContentFile $file) => $file->restore());
    }

    /**
     * İçerik kalıcı silindi: ekler diskten de gidiyor.
     *
     * Satır kalkıp dosya kalsaydı public/uploads altında sahipsiz birikirdi.
     * Servis kapsayıcıdan alınıyor; modelin kurucusuna bağımlılık geçirilemiyor.
     */
    public function purgeFiles(): void
    {
        $files = app(ContentFileService::class);

        $this->files()->withTrashed()->each(fn (ContentFile $file) => $files->delete($file));
    }
}
