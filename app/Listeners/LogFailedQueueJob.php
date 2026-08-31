<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

/**
 * Başarısız işi `failed_jobs` tablosuna yazar.
 *
 * Çerçevede bu işi `queue:work` yapıyor: `WorkCommand` açılışta `JobFailed`
 * olayına abone oluyor ve olayı `queue.failer` sağlayıcısına aktarıyor. Bu
 * proje `queue:work` çalıştıramıyor (pcntl yok — `QueueRunner`'ın var olma
 * sebebi bu), yani **o abone hiç kurulmuyordu.**
 *
 * `Job::fail()` işi siliyor, işin kendi `failed()` metodunu çağırıyor ve
 * `JobFailed` olayını fırlatıyor — ama tabloya yazmıyor. Sonuç: patlayan bir
 * iş sessizce yok oluyordu. `failed_jobs` her zaman boştu, Sistem Sağlık
 * ekranındaki "son 24 saatte başarısız" sayısı her zaman sıfırdı ve Kuyruk
 * ekranı hiçbir zaman dolmayacaktı.
 *
 * Not: `queue:work` bir gün çalıştırılabilir hâle gelirse bu dinleyici ile o
 * komutun kendi abonesi aynı işi iki kez yazar. Projenin tüm kuyruk kurgusu
 * `queue:work`'ün olmadığı varsayımına dayanıyor (bkz. docs/SHARED-HOSTING.md);
 * o varsayım değişirse buranın da gözden geçirilmesi gerekir.
 */
final class LogFailedQueueJob
{
    public function handle(JobFailed $event): void
    {
        try {
            app('queue.failer')?->log(
                $event->connectionName,
                $event->job->getQueue(),
                $event->job->getRawBody(),
                $event->exception,
            );
        } catch (\Throwable $e) {
            // Kayıt tutamamak işin kendisinden daha küçük bir sorun; buradan
            // fırlayan bir hata kuyruğun kalanını da durdururdu.
            Log::warning('Başarısız iş kaydedilemedi', ['error' => $e->getMessage()]);
        }
    }
}
