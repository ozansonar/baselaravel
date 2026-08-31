<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AuditEvent;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Denetim gözlemcisi — kritik modellerde otomatik kayıt.
 *
 * Hangi modellere bağlandığı `AppServiceProvider::boot()` içindeki listede
 * duruyor. Gözlemci yalnızca satır değişikliklerini görür; giriş/çıkış gibi
 * satır değiştirmeyen olaylar için `AuditAuthenticationEvents`, sorgu
 * kurucusundan giden toplu işlemler için servislerin kendi
 * `AuditLogger::custom()` çağrıları var — ikisi de model olayı doğurmuyor.
 */
final class AuditObserver
{
    public function created(Model $model): void
    {
        AuditLogger::log(AuditEvent::Created, $model, [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        AuditLogger::log(AuditEvent::Updated, $model, $model->getOriginal(), $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        AuditLogger::log(AuditEvent::Deleted, $model, $model->getOriginal(), []);
    }

    /**
     * Çöpten geri alma da bir yönetici kararı.
     *
     * Silmenin kaydı varken geri almanınki olmasaydı denetim izi "silindi"
     * diyen ama hâlâ yayında olan kayıtlar gösterirdi.
     */
    public function restored(Model $model): void
    {
        AuditLogger::log(AuditEvent::Updated, $model, [], ['deleted_at' => null]);
    }

    /**
     * Kalıcı silme: geri dönüşü olmayan tek işlem, izi de o yüzden önemli.
     */
    public function forceDeleted(Model $model): void
    {
        AuditLogger::log(AuditEvent::Deleted, $model, $model->getOriginal(), ['kalici' => true]);
    }
}
